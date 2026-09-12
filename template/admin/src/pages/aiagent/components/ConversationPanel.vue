<template>
    <aside class="conversation-panel">
        <div class="panel-brand">AI 对话</div>
        <nav class="panel-nav">
            <div class="nav-item" @click="$emit('create')">
                <Icon type="md-chatbubbles" />
                <span>新建会话</span>
            </div>
            <div class="nav-item" @click="$emit('nav', 'model')">
                <Icon type="md-cube" />
                <span>模型管理</span>
            </div>
            <div class="nav-item" @click="$emit('nav', 'mcp')">
                <Icon type="md-git-network" />
                <span>MCP管理</span>
            </div>
            <div class="nav-item" @click="$emit('nav', 'skill')">
                <Icon type="md-book" />
                <span>Skill管理</span>
            </div>
            <div class="nav-item" @click="$emit('nav', 'task')">
                <Icon type="md-time" />
                <span>自动化任务</span>
            </div>
        </nav>
        <div class="panel-section">会话记录</div>
        <div class="conversation-list">
            <div
                v-for="item in conversations"
                :key="item.id"
                :class="['conversation-item', { active: activeId === item.id }]"
                @click="$emit('select', item)"
            >
                <div class="conversation-info">
                    <span class="conversation-title">{{ item.title }}</span>
                    <span class="conversation-time">{{ formatTime(item.update_time) }}</span>
                </div>
                <div class="conversation-actions">
                    <!-- 三点更多操作：悬停展示 置顶/编辑/删除 菜单（transfer-class-name 覆盖主题对 body 级下拉的暗色染色） -->
                    <Dropdown
                        trigger="hover"
                        placement="bottom-end"
                        transfer
                        transfer-class-name="aiagent-conv-dd"
                        @on-click="onMenuClick($event, item)"
                    >
                        <Icon
                            :class="['action-icon', item.is_pin === 1 ? 'pinned' : 'hoverOnly']"
                            type="md-more"
                            @click.native.stop
                        />
                        <DropdownMenu slot="list">
                            <DropdownItem name="pin">{{ item.is_pin === 1 ? '取消置顶' : '置顶' }}</DropdownItem>
                            <DropdownItem name="rename">编辑</DropdownItem>
                            <DropdownItem name="remove" divided>删除</DropdownItem>
                        </DropdownMenu>
                    </Dropdown>
                </div>
            </div>
            <div v-if="!conversations.length" class="conversation-empty">暂无会话</div>
        </div>
    </aside>
</template>

<script>
/**
 * AI Agent 会话列表面板（样式对齐 crmeb_bz 原版）
 *
 * 品牌标题 + 管理入口导航 + 会话记录（悬停三点菜单：置顶/编辑/删除）。
 */
export default {
    name: 'ConversationPanel',
    props: {
        // 会话列表（置顶优先，组内按更新时间倒序）
        conversations: {
            type: Array,
            default: () => []
        },
        loading: {
            type: Boolean,
            default: false
        },
        // 当前选中会话 ID
        activeId: {
            type: Number,
            default: 0
        }
    },
    methods: {
        /**
         * 三点菜单命令分发：pin=置顶切换 / rename=重命名 / remove=删除
         * @param {String} command 菜单命令
         * @param {Object} item 会话项
         */
        onMenuClick(command, item) {
            this.$emit(command, item);
        },
        /**
         * 会话时间展示：月-日 时:分
         * @param {Number} time 秒级时间戳
         * @returns {String}
         */
        formatTime(time) {
            const timestamp = Number(time);
            if (!timestamp) return '';
            const date = new Date(timestamp * 1000);
            if (Number.isNaN(date.getTime())) return '';
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getMonth() + 1}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        }
    }
};
</script>

<style scoped>
.conversation-panel {
    display: flex;
    width: 240px;
    flex-shrink: 0;
    flex-direction: column;
    padding: 16px 12px;
    overflow-y: auto;
    background: #f7f8fa;
    border-right: 1px solid #ebeef5;
}
.panel-brand {
    margin: 0 8px 16px;
    color: #303133;
    font-size: 17px;
    font-weight: 700;
}
.panel-nav {
    margin-bottom: 8px;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 2px;
    padding: 9px 10px;
    color: #303133;
    font-size: 14px;
    cursor: pointer;
    border-radius: 8px;
}
.nav-item i {
    color: #606266;
    font-size: 16px;
}
.nav-item:hover {
    background: #ececec;
}
.panel-section {
    margin: 12px 8px 6px;
    color: #909399;
    font-size: 12px;
}
.conversation-list {
    flex: 1;
}
.conversation-item {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
    padding: 10px;
    cursor: pointer;
    border-radius: 8px;
    color: #303133;
}
.conversation-item:hover,
.conversation-item.active {
    color: #0256ff;
    background: #fff;
}
.conversation-info {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    /* 右侧留出右上角三点图标的位置，防止标题被遮挡 */
    padding-right: 24px;
}
.conversation-title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.conversation-time {
    margin-top: 4px;
    color: #a8abb2;
    font-size: 12px;
}
.conversation-actions {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
}
.action-icon {
    color: #a8abb2;
    font-size: 16px;
    cursor: pointer;
}
.action-icon:hover {
    color: #0256ff;
}
/* 置顶中的会话：三点图标常显并高亮 */
.action-icon.pinned {
    color: #0256ff;
}
/* 非置顶会话的三点图标：悬停列表项时才显示 */
.action-icon.hoverOnly {
    opacity: 0;
}
.conversation-item:hover .hoverOnly {
    opacity: 1;
}
.conversation-empty {
    color: #a8abb2;
    font-size: 13px;
    text-align: center;
}
</style>

<style>
/* 三点菜单弹出层 transfer 到 body：侧边栏折叠菜单的全局样式（side-menu.vue）会把所有
   body 级下拉统一染成暗色 170px 宽，此处用 transfer-class-name + 更高特异性反压制回白底 */
body > .ivu-select-dropdown.ivu-dropdown-transfer.aiagent-conv-dd {
    min-width: 130px;
    width: auto !important;
    max-height: none !important;
    padding: 5px 0;
    background: #fff !important;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
}
.ivu-select-dropdown.aiagent-conv-dd .ivu-dropdown-menu li.ivu-dropdown-item {
    margin: 0 6px;
    padding: 7px 12px !important;
    color: #303133 !important;
    font-size: 13px;
    line-height: 1.6;
    text-align: left;
    border-radius: 6px;
}
.ivu-select-dropdown.aiagent-conv-dd .ivu-dropdown-menu li.ivu-dropdown-item:hover {
    color: #0256ff !important;
    background-color: #f2f3f5 !important;
}
.ivu-select-dropdown.aiagent-conv-dd .ivu-dropdown-menu li.ivu-dropdown-item.ivu-dropdown-item-divided {
    margin-top: 5px;
    border-top: 1px solid #ebeef5;
}
.ivu-select-dropdown.aiagent-conv-dd .ivu-dropdown-menu li.ivu-dropdown-item.ivu-dropdown-item-divided:before {
    display: none;
}
</style>
