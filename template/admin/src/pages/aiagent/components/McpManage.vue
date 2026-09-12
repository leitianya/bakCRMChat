<template>
    <div class="mcp-page">
        <!-- 顶部说明区 -->
        <div class="mcp-hero">
            <div class="mcp-hero-icon"><Icon type="md-git-network" /></div>
            <div class="mcp-hero-main">
                <div class="mcp-hero-title">MCP 服务管理</div>
                <div class="mcp-hero-sub">安装 MCP 服务，为 AI Agent 扩展更多工具能力</div>
            </div>
            <Button type="primary" icon="md-settings" @click="openEditor()">配置 MCP</Button>
        </div>

        <div class="mcp-toolbar">
            <Input
                v-model="keyword"
                placeholder="搜索 MCP"
                clearable
                class="mcp-search"
            ></Input>
        </div>

        <div class="mcp-section">
            <span class="mcp-section-title">我的 MCP</span>
            <span class="mcp-count-badge">{{ filteredList.length }}</span>
            <span class="mcp-enabled-count">{{ enabledCount }} 启用</span>
        </div>

        <div class="mcp-list">
            <div
                v-for="row in filteredList"
                :key="row.id"
                class="mcp-card"
                :class="{ 'is-expanded': isExpanded(row.id) }"
            >
                <div class="mcp-card-head">
                    <span class="mcp-chevron" @click="toggleExpand(row.id)">
                        <Icon :type="isExpanded(row.id) ? 'ios-arrow-down' : 'ios-arrow-forward'" />
                    </span>
                    <span class="mcp-avatar" :class="{ 'is-system': row.is_system }" :style="row.is_system ? {} : { background: avatarColor(row) }">{{ avatarText(row) }}</span>
                    <div class="mcp-meta">
                        <div class="mcp-name">
                            <span class="mcp-name-text">{{ row.name }}</span>
                            <span v-if="row.is_system" class="mcp-system-badge">内置</span>
                            <span class="mcp-dot" :class="dotClass(row)" :title="dotTitle(row)"></span>
                        </div>
                        <div class="mcp-sub">{{ toolSummary(row) }}</div>
                    </div>
                    <div class="mcp-actions">
                        <template v-if="!row.is_system">
                            <Tooltip content="测试连接" transfer>
                                <Icon
                                    type="md-link"
                                    :class="['mcp-action-icon', { 'is-loading': testingId === row.id }]"
                                    @click.native="testServer(row)"
                                />
                            </Tooltip>
                            <Tooltip content="同步工具" transfer>
                                <Icon
                                    type="md-refresh"
                                    :class="['mcp-action-icon', { 'is-loading': syncingId === row.id }]"
                                    @click.native="syncTools(row)"
                                />
                            </Tooltip>
                            <Tooltip content="编辑" transfer>
                                <Icon type="md-create" class="mcp-action-icon" @click.native="openEditor(row)" />
                            </Tooltip>
                            <Tooltip content="删除" transfer>
                                <Icon type="md-trash" class="mcp-action-icon danger" @click.native="removeServer(row)" />
                            </Tooltip>
                            <i-switch
                                v-model="row.status"
                                :true-value="1"
                                :false-value="0"
                                class="mcp-switch"
                                @on-change="changeStatus(row)"
                            ></i-switch>
                        </template>
                    </div>
                </div>

                <div v-if="isExpanded(row.id)" class="mcp-card-body">
                    <div v-if="!row.is_system" class="mcp-url mono">
                        <span class="muted">接口地址</span>
                        <span class="mcp-url-text">{{ row.url }}</span>
                        <span v-if="row.tools_sync_time" class="muted">{{ formatTime(row.tools_sync_time) }}</span>
                    </div>
                    <div v-if="!row.tools.length" class="muted mcp-no-tools">
                        {{ row.is_system ? '内置工具加载异常，请检查系统日志。' : '尚未同步工具，点击同步图标拉取远端工具清单。' }}
                    </div>
                    <div v-else class="mcp-tools">
                        <Tooltip v-for="tool in row.tools" :key="tool.name" :content="tool.description || '暂无描述'" transfer>
                            <span class="tool-chip mono">{{ tool.name }}</span>
                        </Tooltip>
                    </div>
                </div>
            </div>

            <div v-if="!filteredList.length" class="mcp-empty">
                <template v-if="list.length">未找到匹配「{{ keyword }}」的 MCP 服务</template>
                <template v-else>还没有 MCP 服务，点击右上角「配置 MCP」粘贴 mcpServers JSON 接入</template>
            </div>
        </div>

        <!-- mcpServers JSON 配置弹窗（新增支持多条，编辑单条） -->
        <Modal v-model="editorVisible" :title="editId ? '编辑 MCP Server' : '新增 MCP Server'" width="720" :mask-closable="false">
            <div class="dialog-tip">
                直接粘贴 mcpServers JSON 配置即可，url 需支持 Streamable HTTP。
                <template v-if="editId">编辑时配置中只能包含一条记录，__KEEP__ 或留空表示保持原密钥。</template>
                <template v-else>支持一次粘贴多个 Server，本地进程（command）类型暂不支持。</template>
                <a v-if="!editId" class="load-example" @click="loadExample">加载示例</a>
            </div>
            <Input
                v-model="jsonText"
                type="textarea"
                :rows="16"
                class="json-input"
                placeholder='{"mcpServers":{"example":{"url":"https://example.com/mcp","headers":{"Authorization":"Bearer xxx"}}}}'
            ></Input>
            <div slot="footer">
                <Button @click="editorVisible = false">取消</Button>
                <Button type="primary" :loading="saving" @click="saveServer">保存</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
/**
 * MCP Server 管理（样式对齐 crmeb_bz 原版）
 *
 * hero 说明区 + 搜索 + 「我的 MCP」卡片列表（彩色首字母头像、状态圆点、
 * 展开/收起查看工具清单 chip）；配置统一走 mcpServers JSON 弹窗。
 */
import {
    aiMcpServerListApi,
    aiMcpServerDetailApi,
    aiMcpServerSaveApi,
    aiMcpServerDeleteApi,
    aiMcpServerStatusApi,
    aiMcpServerSyncApi,
    aiMcpServerTestApi
} from '@/api/aiagent';

const AVATAR_COLORS = ['#ff7a45', '#9254de', '#40a9ff', '#73d13d', '#f759ab', '#ffc53d', '#36cfc9', '#597ef7'];

export default {
    name: 'McpManage',
    data() {
        return {
            loading: false,
            saving: false,
            syncingId: 0,
            testingId: 0,
            editorVisible: false,
            list: [],
            editId: 0,
            jsonText: '',
            keyword: '',
            expandedIds: []
        };
    },
    computed: {
        filteredList() {
            const kw = this.keyword.trim().toLowerCase();
            if (!kw) return this.list;
            return this.list.filter((row) => {
                const fields = [row.name, row.server_key, row.url, row.description];
                const tools = (row.tools || []).map((t) => t.name);
                return fields.concat(tools).some((v) => String(v || '').toLowerCase().indexOf(kw) !== -1);
            });
        },
        enabledCount() {
            return this.list.filter((row) => row.status === 1).length;
        }
    },
    mounted() {
        this.loadList();
    },
    methods: {
        loadList() {
            this.loading = true;
            aiMcpServerListApi()
                .then((res) => {
                    this.list = res.data || [];
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        // 搜索时全部展开
        isExpanded(id) {
            return this.keyword.trim() !== '' || this.expandedIds.indexOf(id) !== -1;
        },
        toggleExpand(id) {
            const idx = this.expandedIds.indexOf(id);
            if (idx >= 0) this.expandedIds.splice(idx, 1);
            else this.expandedIds.push(id);
        },
        avatarText(row) {
            const name = String(row.name || row.server_key || '?');
            return name.charAt(0).toUpperCase();
        },
        avatarColor(row) {
            const key = String(row.server_key || row.name || '');
            let hash = 0;
            for (let i = 0; i < key.length; i++) {
                hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
            }
            return AVATAR_COLORS[hash % AVATAR_COLORS.length];
        },
        dotClass(row) {
            if (row.is_system) return 'is-on';
            if (row.status !== 1) return 'is-off';
            return row.tools_sync_time ? 'is-on' : 'is-pending';
        },
        dotTitle(row) {
            if (row.is_system) return '随系统启用';
            if (row.status !== 1) return '已停用';
            return row.tools_sync_time ? '已连接' : '未同步工具';
        },
        toolSummary(row) {
            if (row.is_system) return `${row.tool_count} 个工具 · 随系统启用`;
            if (!row.tools_sync_time) return '未同步工具';
            return `${row.tool_count} 个工具 · ${this.formatTime(row.tools_sync_time)}`;
        },
        formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            const pad = (n) => String(n).padStart(2, '0');
            return `同步于 ${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
        /**
         * 打开配置弹窗（无参为新增；编辑时把详情还原为 mcpServers JSON）
         * @param {Object|undefined} row Server 卡片行
         */
        openEditor(row) {
            this.editorVisible = true;
            if (!row) {
                this.editId = 0;
                this.jsonText = '';
                return;
            }
            aiMcpServerDetailApi(row.id).then((res) => {
                const data = res.data || {};
                this.editId = data.id;
                const item = { name: data.name, url: data.url };
                // format 接口对已设置密钥回显 __KEEP__ 占位符，原样粘贴即可保持密钥不变
                if (data.api_key) item.api_key = data.api_key;
                if (data.headers && Object.keys(data.headers).length) item.headers = data.headers;
                if (data.timeout) item.timeout = data.timeout;
                if (data.description) item.description = data.description;
                if (data.status !== 1) item.disabled = true;
                this.jsonText = JSON.stringify({ mcpServers: { [data.server_key]: item } }, null, 2);
            });
        },
        // 填充示例配置
        loadExample() {
            this.jsonText = JSON.stringify(
                {
                    mcpServers: {
                        'example-server': {
                            url: 'https://example.com/mcp',
                            headers: { Authorization: 'Bearer your-token' },
                            timeout: 15,
                            description: '示例远程 MCP Server'
                        }
                    }
                },
                null,
                2
            );
        },
        saveServer() {
            const text = this.jsonText.trim();
            if (!text) {
                this.$Message.warning('请粘贴 mcpServers JSON 配置');
                return;
            }
            try {
                const parsed = JSON.parse(text);
                if (!parsed.mcpServers || !Object.keys(parsed.mcpServers).length) {
                    this.$Message.warning('配置需包含 mcpServers 节点');
                    return;
                }
            } catch (e) {
                this.$Message.error('JSON 格式不正确：' + e.message);
                return;
            }
            this.saving = true;
            aiMcpServerSaveApi({ config: text }, this.editId)
                .then((res) => {
                    this.$Message.success(res.msg || '保存成功');
                    this.editorVisible = false;
                    this.loadList();
                    this.$emit('changed');
                })
                .finally(() => {
                    this.saving = false;
                });
        },
        changeStatus(row) {
            aiMcpServerStatusApi(row.id, row.status)
                .then(() => {
                    this.$Message.success('修改成功');
                    this.$emit('changed');
                })
                .catch(() => {
                    row.status = row.status === 1 ? 0 : 1;
                });
        },
        testServer(row) {
            if (this.testingId) return;
            this.testingId = row.id;
            aiMcpServerTestApi(row.id)
                .then((res) => {
                    const data = res.data || {};
                    if (data.ok) {
                        this.$Message.success(`${data.message}，耗时 ${data.cost_ms}ms`);
                    } else {
                        this.$Message.error(data.message || '连接失败');
                    }
                })
                .catch(() => {})
                .finally(() => {
                    this.testingId = 0;
                });
        },
        syncTools(row) {
            if (this.syncingId) return;
            this.syncingId = row.id;
            aiMcpServerSyncApi(row.id)
                .then((res) => {
                    const count = (res.data || {}).count || 0;
                    this.$Message.success(`同步成功，共 ${count} 个工具`);
                    this.loadList();
                    this.$emit('changed');
                })
                .catch(() => {})
                .finally(() => {
                    this.syncingId = 0;
                });
        },
        removeServer(row) {
            this.$Modal.confirm({
                title: '提示',
                content: `确定删除 MCP 服务“${row.name}”吗？`,
                onOk: () => {
                    aiMcpServerDeleteApi(row.id).then(() => {
                        this.$Message.success('删除成功');
                        this.loadList();
                        this.$emit('changed');
                    });
                }
            });
        }
    }
};
</script>

<style scoped>
.mono {
    font-family: Menlo, Consolas, monospace;
    font-size: 12px;
}
.muted {
    color: #909399;
    font-size: 12px;
}
.danger {
    color: #f56c6c;
}
/* 顶部说明区 */
.mcp-hero {
    display: flex;
    align-items: center;
    padding: 4px 0 20px;
}
.mcp-hero-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    margin-right: 14px;
    border-radius: 10px;
    background: #f2f3f5;
    color: #303133;
    font-size: 22px;
}
.mcp-hero-main {
    flex: 1;
}
.mcp-hero-title {
    color: #1d2129;
    font-size: 17px;
    font-weight: 600;
}
.mcp-hero-sub {
    margin-top: 4px;
    color: #86909c;
    font-size: 13px;
}
/* 搜索行 */
.mcp-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 14px;
}
.mcp-search {
    width: 100%;
}
/* 分组标题 */
.mcp-section {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}
.mcp-section-title {
    color: #1d2129;
    font-size: 15px;
    font-weight: 600;
}
.mcp-count-badge {
    margin-left: 8px;
    padding: 1px 9px;
    border-radius: 10px;
    background: #f2f3f5;
    color: #4e5969;
    font-size: 12px;
}
.mcp-enabled-count {
    margin-left: auto;
    color: #86909c;
    font-size: 13px;
}
/* Server 卡片 */
.mcp-list {
    min-height: 80px;
}
.mcp-card {
    margin-bottom: 12px;
    border: 1px solid #f0f1f3;
    border-radius: 12px;
    background: #fff;
    transition: box-shadow 0.2s;
}
.mcp-card:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}
.mcp-card.is-expanded {
    border-color: #e5e6eb;
}
.mcp-card-head {
    display: flex;
    align-items: center;
    padding: 14px 16px;
}
.mcp-chevron {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    margin-right: 6px;
    border-radius: 4px;
    color: #86909c;
    font-size: 12px;
    cursor: pointer;
}
.mcp-chevron:hover {
    background: #f2f3f5;
}
.mcp-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    margin-right: 12px;
    border-radius: 10px;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
}
.mcp-avatar.is-system {
    background: linear-gradient(135deg, #0256ff, #597ef7);
}
.mcp-system-badge {
    flex-shrink: 0;
    margin-left: 8px;
    padding: 0 6px;
    border-radius: 4px;
    background: #e8f1ff;
    color: #0256ff;
    font-size: 11px;
    line-height: 18px;
    font-weight: 500;
}
.mcp-meta {
    flex: 1;
    min-width: 0;
}
.mcp-name {
    display: flex;
    align-items: center;
}
.mcp-name-text {
    color: #1d2129;
    font-size: 14px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mcp-dot {
    flex-shrink: 0;
    width: 8px;
    height: 8px;
    margin-left: 8px;
    border-radius: 50%;
}
.mcp-dot.is-on {
    background: #00b42a;
}
.mcp-dot.is-pending {
    background: #ff7d00;
}
.mcp-dot.is-off {
    background: #c9cdd4;
}
.mcp-sub {
    margin-top: 3px;
    color: #86909c;
    font-size: 12px;
}
.mcp-actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}
.mcp-action-icon {
    margin-left: 18px;
    color: #4e5969;
    font-size: 16px;
    cursor: pointer;
}
.mcp-action-icon:hover {
    color: #1d2129;
}
.mcp-action-icon.danger:hover {
    color: #f56c6c;
}
.mcp-action-icon.is-loading {
    animation: mcp-spin 1s linear infinite;
    pointer-events: none;
    opacity: 0.6;
}
.mcp-switch {
    margin-left: 18px;
}
@keyframes mcp-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
/* 展开区 */
.mcp-card-body {
    padding: 0 16px 16px 76px;
}
.mcp-url {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.mcp-url-text {
    color: #4e5969;
    word-break: break-all;
}
.mcp-no-tools {
    font-size: 13px;
}
.mcp-tools {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.tool-chip {
    padding: 5px 12px;
    border-radius: 8px;
    background: #f2f3f5;
    color: #1d2129;
    font-size: 12px;
    cursor: default;
    transition: background 0.15s;
}
.tool-chip:hover {
    background: #e5e6eb;
}
/* 空态 */
.mcp-empty {
    padding: 40px 0;
    color: #86909c;
    font-size: 13px;
    text-align: center;
}
/* 弹窗 */
.dialog-tip {
    margin-bottom: 10px;
    color: #909399;
    font-size: 13px;
    line-height: 1.6;
}
.load-example {
    color: #0256ff;
    margin-left: 6px;
    cursor: pointer;
}
</style>
<style>
/* JSON 输入等宽字体（textarea 为 iView 内部元素，全局穿透） */
.json-input textarea {
    font-family: Menlo, Consolas, monospace;
    font-size: 12px;
    line-height: 1.7;
}
</style>
