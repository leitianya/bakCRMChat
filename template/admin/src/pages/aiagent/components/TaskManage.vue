<template>
    <div class="task-page">
        <!-- 顶部说明区 -->
        <div class="task-hero">
            <div class="task-hero-icon"><Icon type="md-time" /></div>
            <div class="task-hero-main">
                <div class="task-hero-title">自动化任务</div>
                <div class="task-hero-sub">让 AI 按计划无人值守执行指令并推送结果（如每日经营日报）</div>
            </div>
            <Button type="primary" icon="md-add" @click="openEditor()">新建任务</Button>
        </div>

        <div class="task-section">
            <span class="task-section-title">我的任务</span>
            <span class="task-count-badge">{{ list.length }}</span>
            <span class="task-enabled-count">{{ enabledCount }} 启用</span>
        </div>

        <!-- 任务卡片列表 -->
        <div class="task-list">
            <div v-for="row in list" :key="row.id" class="task-card">
                <div class="task-card-head">
                    <span class="task-avatar" :style="{ background: avatarColor(row) }">{{ avatarText(row) }}</span>
                    <div class="task-meta">
                        <div class="task-name">
                            <span class="task-name-text">{{ row.name }}</span>
                            <Tag :color="autonomyTag(row.autonomy).color" size="small" class="task-tag">
                                {{ autonomyTag(row.autonomy).label }}
                            </Tag>
                        </div>
                        <div class="task-sub">
                            {{ scheduleText(row) }}
                            <template v-if="row.next_run_time"> · 下次 {{ formatTime(row.next_run_time) }}</template>
                            <template v-if="row.last_run_time"> · 上次 {{ formatTime(row.last_run_time) }}</template>
                        </div>
                    </div>
                    <div class="task-actions">
                        <Tooltip content="运行历史" transfer>
                            <Icon type="md-list" class="task-action-icon" @click.native="openRuns(row)" />
                        </Tooltip>
                        <Tooltip content="测试运行：立即执行一次并打开运行历史查看结果" transfer>
                            <Icon
                                type="md-play"
                                :class="['task-action-icon', { 'is-loading': triggeringId === row.id }]"
                                @click.native="triggerTask(row)"
                            />
                        </Tooltip>
                        <Tooltip content="编辑" transfer>
                            <Icon type="md-create" class="task-action-icon" @click.native="openEditor(row)" />
                        </Tooltip>
                        <Tooltip content="删除" transfer>
                            <Icon type="md-trash" class="task-action-icon danger" @click.native="removeTask(row)" />
                        </Tooltip>
                        <i-switch
                            v-model="row.status"
                            :true-value="1"
                            :false-value="0"
                            class="task-switch"
                            @on-change="changeStatus(row)"
                        ></i-switch>
                    </div>
                </div>

                <div class="task-card-body">
                    <div class="task-instruction" :title="row.instruction">{{ row.instruction }}</div>
                    <div class="task-channel-row">
                        <template v-if="row.notify_channels && row.notify_channels.length">
                            <Tag
                                v-for="channel in row.notify_channels"
                                :key="channel"
                                size="small"
                                color="default"
                                class="channel-tag"
                            >
                                {{ channelLabel(channel) }}
                            </Tag>
                            <span v-if="row.notify_channels.indexOf('is_email') !== -1 && row.notify_email" class="muted">{{ row.notify_email }}</span>
                        </template>
                        <span v-else class="muted">未配置通知渠道</span>
                    </div>
                </div>
            </div>

            <div v-if="!list.length" class="task-empty">还没有自动化任务，点击右上角「新建任务」创建第一个定时任务</div>
        </div>

        <div v-if="total > limit" class="task-pagination">
            <Page :total="total" :current="page" :page-size="limit" show-total @on-change="(p) => ((page = p), loadList())"></Page>
        </div>

        <!-- 新建/编辑任务 -->
        <Modal v-model="editorVisible" :title="editId ? '编辑任务' : '新建任务'" width="680" :mask-closable="false">
            <Alert type="info" show-icon class="editor-tip">
                任务指令是 AI 每次无人值守执行时收到的完整说明，请写明数据范围、分析要求与交付动作（如生成日报并发送到邮箱）。
            </Alert>
            <Form :model="form" :rules="rules" :label-width="100" class="task-form">
                <FormItem label="任务名称" prop="name">
                    <Input v-model="form.name" :maxlength="100" placeholder="如：每日经营日报"></Input>
                </FormItem>
                <FormItem label="任务指令" prop="instruction">
                    <Input
                        v-model="form.instruction"
                        type="textarea"
                        :rows="5"
                        :maxlength="4000"
                        show-word-limit
                        placeholder="如：查询昨天的会话量、新增用户数与客服响应情况，生成服务日报（含环比分析）；完成后通过配置的通知渠道发送"
                    ></Input>
                </FormItem>
                <FormItem label="执行时间" prop="schedule_value">
                    <RadioGroup v-model="form.schedule_type" @on-change="onScheduleTypeChange">
                        <Radio :label="2">每天定时</Radio>
                        <Radio :label="3">每周定时</Radio>
                        <Radio :label="4">每月定时</Radio>
                        <Radio :label="5">每年定时</Radio>
                        <Radio :label="1">间隔循环</Radio>
                    </RadioGroup>
                    <div class="schedule-input">
                        <Select v-if="form.schedule_type === 2" v-model="form.schedule_value" class="schedule-field">
                            <Option v-for="t in TIME_OPTIONS" :key="t" :value="t">{{ t }}</Option>
                        </Select>
                        <template v-else-if="form.schedule_type === 3">
                            <Select v-model="form.schedule_week" class="schedule-field">
                                <Option v-for="n in 7" :key="n" :value="n">{{ WEEK_LABELS[n] }}</Option>
                            </Select>
                            <Select v-model="form.schedule_value" class="schedule-field">
                                <Option v-for="t in TIME_OPTIONS" :key="t" :value="t">{{ t }}</Option>
                            </Select>
                        </template>
                        <template v-else-if="form.schedule_type === 4">
                            <span class="muted">每月</span>
                            <InputNumber
                                v-model="form.schedule_day"
                                :min="1"
                                :max="28"
                                :controls="false"
                                placeholder="几日"
                                class="schedule-day-field"
                            ></InputNumber>
                            <span class="muted">日</span>
                            <Select v-model="form.schedule_value" class="schedule-field">
                                <Option v-for="t in TIME_OPTIONS" :key="t" :value="t">{{ t }}</Option>
                            </Select>
                        </template>
                        <template v-else-if="form.schedule_type === 5">
                            <Select v-model="form.schedule_month" class="schedule-month-field">
                                <Option v-for="n in 12" :key="n" :value="n">{{ n }}月</Option>
                            </Select>
                            <InputNumber
                                v-model="form.schedule_day"
                                :min="1"
                                :max="28"
                                :controls="false"
                                placeholder="几日"
                                class="schedule-day-field"
                            ></InputNumber>
                            <span class="muted">日</span>
                            <Select v-model="form.schedule_value" class="schedule-field">
                                <Option v-for="t in TIME_OPTIONS" :key="t" :value="t">{{ t }}</Option>
                            </Select>
                        </template>
                        <template v-else>
                            <InputNumber
                                v-model="scheduleMinutes"
                                :min="1"
                                :max="43200"
                                :controls="false"
                                placeholder="间隔分钟"
                                class="schedule-field"
                            ></InputNumber>
                            <span class="muted">分钟（1-43200，即最多 30 天）</span>
                        </template>
                    </div>
                </FormItem>
                <FormItem label="自主级别">
                    <RadioGroup v-model="form.autonomy">
                        <Radio :label="1">只读（推荐）</Radio>
                        <Radio :label="3">全自动</Radio>
                        <Radio :label="2" disabled>需审批（即将上线）</Radio>
                    </RadioGroup>
                    <div class="muted autonomy-tip">{{ autonomyTip(form.autonomy) }}</div>
                </FormItem>
                <FormItem label="模型">
                    <Select v-model="form.model_id" placeholder="系统默认" clearable class="task-field">
                        <Option v-for="item in options.models" :key="item.id" :label="item.name" :value="item.id"></Option>
                    </Select>
                </FormItem>
                <FormItem label="连接器">
                    <Select v-model="form.server_ids" multiple placeholder="默认挂载全部可用" class="task-field">
                        <Option v-for="item in options.mcp_servers" :key="item.id" :label="item.name" :value="item.id"></Option>
                    </Select>
                </FormItem>
                <FormItem label="Skill">
                    <Select v-model="form.skill_keys" multiple placeholder="可选：挂载领域规则" class="task-field">
                        <Option v-for="item in options.skills" :key="item.key" :label="item.name" :value="item.key"></Option>
                    </Select>
                </FormItem>
                <FormItem label="通知渠道">
                    <CheckboxGroup v-model="form.notify_channels">
                        <Checkbox label="is_email">邮件</Checkbox>
                        <Checkbox label="is_ent_wechat">企业微信机器人</Checkbox>
                    </CheckboxGroup>
                    <Input
                        v-if="form.notify_channels.indexOf('is_email') !== -1"
                        v-model="form.notify_email"
                        placeholder="收件邮箱"
                        class="task-field email-field"
                    ></Input>
                </FormItem>
                <FormItem label="高级参数">
                    <div class="advanced-row">
                        <span class="muted">工具轮数上限</span>
                        <InputNumber v-model="form.max_steps" :min="3" :max="30" :controls="false" class="advanced-field"></InputNumber>
                        <span class="muted">软超时（秒）</span>
                        <InputNumber
                            v-model="form.timeout"
                            :min="60"
                            :max="1800"
                            :step="30"
                            :controls="false"
                            class="advanced-field"
                        ></InputNumber>
                    </div>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="editorVisible = false">取消</Button>
                <Button type="primary" :loading="saving" @click="saveTask">保存</Button>
            </div>
        </Modal>

        <!-- 运行历史 -->
        <Modal v-model="runsVisible" :title="`运行历史 · ${currentTask.name || ''}`" width="860" :mask-closable="true">
            <Table :columns="runColumns" :data="runs" :loading="runsLoading" size="small">
                <template slot-scope="{ row }" slot="status">
                    <Tag :color="runStatusTag(row.status).color" size="small">{{ runStatusTag(row.status).label }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="result">
                    <span v-if="row.summary" class="cell-ellipsis" :title="row.summary">{{ row.summary }}</span>
                    <span v-else-if="row.error" class="cell-error cell-ellipsis" :title="row.error">{{ row.error }}</span>
                    <span v-else class="muted">—</span>
                </template>
            </Table>
            <div v-if="runsTotal > runsLimit" class="task-pagination">
                <Page
                    :total="runsTotal"
                    :current="runsPage"
                    :page-size="runsLimit"
                    show-total
                    @on-change="(p) => ((runsPage = p), loadRuns())"
                ></Page>
            </div>
            <div slot="footer">
                <Button @click="runsVisible = false">关闭</Button>
            </div>
        </Modal>

        <!-- 运行详情（过程回放） -->
        <Modal v-model="detailVisible" title="运行详情" width="760" :mask-closable="true">
            <div class="run-detail">
                <template v-if="runDetail">
                    <div class="run-detail-head">
                        <Tag :color="runStatusTag(runDetail.status).color" size="small">
                            {{ runStatusTag(runDetail.status).label }}
                        </Tag>
                        <span v-if="runDetail.started_at" class="muted">{{ formatFullTime(runDetail.started_at) }}</span>
                        <span v-if="runDetail.duration" class="muted">耗时 {{ runDetail.duration }}s</span>
                        <span class="muted">工具调用 {{ runDetail.tool_calls }} 次</span>
                    </div>
                    <div v-if="runDetail.error" class="run-detail-error">{{ runDetail.error }}</div>
                    <div class="run-messages">
                        <template v-for="(message, index) in runDetail.messages || []">
                            <div v-if="message.role === 'user'" :key="'u' + index" class="msg-row user">
                                <div class="msg-bubble user">{{ message.content }}</div>
                            </div>
                            <div v-else-if="message.role === 'tool'" :key="'t' + index" class="msg-tool">
                                <div class="tool-line">
                                    <Icon type="md-git-network" />
                                    <span>工具 {{ message.tool_name }}</span>
                                </div>
                                <div v-if="toolResultBrief(message)" class="tool-result mono">{{ toolResultBrief(message) }}</div>
                            </div>
                            <div v-else-if="message.role === 'assistant' && message.content" :key="'a' + index" class="msg-row assistant">
                                <div class="msg-content" v-html="renderMarkdown(message.content)"></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            <div slot="footer">
                <Button @click="detailVisible = false">关闭</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
/**
 * AI 自动化任务管理（样式对齐 crmeb_bz 原版）
 *
 * hero 说明区 + 任务卡片列表（头像/自主级别标签/调度摘要/通知渠道标签）
 * + 新建编辑弹窗（执行时间单选切换 + 模型/连接器/Skill 挂载 + 高级参数）
 * + 运行历史表格 + 运行详情过程回放。
 */
import { mdToHtml } from '../markdown';
import {
    aiAgentOptionsApi,
    aiTaskListApi,
    aiTaskDetailApi,
    aiTaskSaveApi,
    aiTaskDeleteApi,
    aiTaskStatusApi,
    aiTaskTriggerApi,
    aiTaskRunsApi,
    aiTaskRunListApi,
    aiTaskRunDetailApi
} from '@/api/aiagent';

const AVATAR_COLORS = ['#ff7a45', '#9254de', '#40a9ff', '#73d13d', '#f759ab', '#ffc53d', '#36cfc9', '#597ef7'];
const WEEK_LABELS = { 1: '周一', 2: '周二', 3: '周三', 4: '周四', 5: '周五', 6: '周六', 7: '周日' };
const CHANNEL_LABELS = { is_email: '邮件', is_ent_wechat: '企业微信' };
const RUN_STATUS = {
    success: { label: '成功', color: 'green' },
    failed: { label: '失败', color: 'red' },
    timeout: { label: '超时', color: 'orange' },
    running: { label: '执行中', color: 'blue' }
};
// 半小时粒度时刻选项（与原版 el-time-select 步长一致）
const TIME_OPTIONS = (() => {
    const options = [];
    for (let h = 0; h < 24; h++) {
        options.push(`${String(h).padStart(2, '0')}:00`);
        options.push(`${String(h).padStart(2, '0')}:30`);
    }
    return options;
})();

const emptyForm = () => ({
    id: 0,
    name: '',
    instruction: '',
    model_id: 0,
    server_ids: [],
    skill_keys: [],
    autonomy: 1,
    schedule_type: 2,
    schedule_value: '09:00',
    schedule_week: 1,
    schedule_day: 1,
    schedule_month: 1,
    max_steps: 10,
    timeout: 300,
    notify_channels: [],
    notify_email: '',
    status: 1
});

export default {
    name: 'TaskManage',
    data() {
        return {
            TIME_OPTIONS,
            WEEK_LABELS,
            loading: false,
            triggeringId: 0,
            list: [],
            page: 1,
            limit: 10,
            total: 0,
            enabledCount: 0,
            editorVisible: false,
            editId: 0,
            saving: false,
            options: { models: [], mcp_servers: [], skills: [] },
            scheduleMinutes: 30,
            form: emptyForm(),
            rules: {
                name: [{ required: true, message: '请填写任务名称', trigger: 'blur' }],
                instruction: [{ required: true, message: '请填写任务指令', trigger: 'blur' }]
            },
            runsVisible: false,
            runsLoading: false,
            runs: [],
            runsPage: 1,
            runsLimit: 10,
            runsTotal: 0,
            currentTask: { name: '' },
            detailVisible: false,
            detailLoading: false,
            runDetail: null,
            runColumns: [
                {
                    title: '状态',
                    key: 'status',
                    width: 90,
                    slot: 'status'
                },
                {
                    title: '触发',
                    key: 'trigger_type',
                    width: 80,
                    render: (h, params) => h('span', params.row.trigger_type === 2 ? '手动' : '调度')
                },
                {
                    title: '开始时间',
                    key: 'started_at',
                    width: 150,
                    render: (h, params) => h('span', params.row.started_at ? this.formatFullTime(params.row.started_at) : '—')
                },
                {
                    title: '耗时',
                    key: 'duration',
                    width: 80,
                    render: (h, params) => h('span', params.row.duration ? params.row.duration + 's' : '—')
                },
                { title: '工具调用', key: 'tool_calls', width: 80 },
                {
                    title: '结果 / 错误',
                    key: 'summary',
                    minWidth: 220,
                    slot: 'result'
                },
                {
                    title: '操作',
                    key: 'action',
                    width: 70,
                    render: (h, params) =>
                        h('a', { class: 'aiagent-link', on: { click: () => this.openRunDetail(params.row) } }, '详情')
                }
            ]
        };
    },
    mounted() {
        this.loadOptions();
        this.loadList();
    },
    methods: {
        renderMarkdown(content) {
            return mdToHtml(content);
        },
        avatarText(row) {
            return String(row.name || '?').charAt(0).toUpperCase();
        },
        avatarColor(row) {
            const key = String(row.id || row.name || '');
            let hash = 0;
            for (let i = 0; i < key.length; i++) {
                hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
            }
            return AVATAR_COLORS[hash % AVATAR_COLORS.length];
        },
        autonomyTag(autonomy) {
            return autonomy === 3 ? { label: '全自动', color: 'orange' } : { label: '只读', color: 'geekblue' };
        },
        autonomyTip(autonomy) {
            return autonomy === 3
                ? '全自动模式下 AI 可直接执行全部工具，请确认任务边界后再启用'
                : '只读模式下 AI 仅能查询数据，需确认的写操作不可见，结果经通知渠道推送';
        },
        channelLabel(channel) {
            return CHANNEL_LABELS[channel] || channel;
        },
        runStatusTag(status) {
            return RUN_STATUS[status] || { label: status, color: 'default' };
        },
        // 调度配置 → 中文摘要
        scheduleText(row) {
            switch (row.schedule_type) {
                case 1:
                    return `每隔 ${row.schedule_value} 分钟`;
                case 2:
                    return `每天 ${row.schedule_value}`;
                case 3:
                    return `每周${(WEEK_LABELS[String(row.schedule_value).charAt(0)] || '').replace('周', '')} ${String(row.schedule_value).slice(2)}`;
                case 4:
                    return `每月 ${String(row.schedule_value).replace('|', ' 日 ')} 执行`;
                case 5: {
                    const parts = String(row.schedule_value).split('|');
                    return `每年 ${parts[0]} 月 ${parts[1]} 日 ${parts[2]} 执行`;
                }
                default:
                    return row.schedule_value;
            }
        },
        formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            const pad = (value) => String(value).padStart(2, '0');
            return `${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
        formatFullTime(timestamp) {
            const date = new Date(timestamp * 1000);
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
        },
        loadOptions() {
            aiAgentOptionsApi().then((res) => {
                this.options = { models: [], mcp_servers: [], skills: [], ...(res.data || {}) };
            });
        },
        loadList() {
            this.loading = true;
            aiTaskListApi({ page: this.page, limit: this.limit })
                .then((res) => {
                    const data = res.data || {};
                    this.list = data.list || [];
                    this.total = data.count || 0;
                    this.enabledCount = this.list.filter((row) => row.status === 1).length;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        /**
         * 执行时间类型切换：切到间隔循环时初始化分钟数，其余初始化时刻
         * @param {Number} type 调度类型
         */
        onScheduleTypeChange(type) {
            if (type === 1) {
                this.scheduleMinutes = 30;
            } else if (!TIME_OPTIONS.includes(this.form.schedule_value)) {
                this.form.schedule_value = '09:00';
            }
        },
        /**
         * 打开编辑弹窗（无参为新建；编辑时拉详情还原调度字段）
         * @param {Object|undefined} row 任务行
         */
        openEditor(row) {
            if (!row) {
                this.editId = 0;
                this.form = emptyForm();
                this.scheduleMinutes = 30;
                this.editorVisible = true;
                return;
            }
            aiTaskDetailApi(row.id).then((res) => {
                const data = res.data || {};
                this.editId = data.id;
                this.form = Object.assign(emptyForm(), data);
                // schedule_value 还原到分字段
                const parts = String(data.schedule_value || '').split('|');
                if (data.schedule_type === 3) {
                    this.form.schedule_week = Number(parts[0]) || 1;
                    this.form.schedule_value = parts[1] || '09:00';
                } else if (data.schedule_type === 4) {
                    this.form.schedule_day = Number(parts[0]) || 1;
                    this.form.schedule_value = parts[1] || '09:00';
                } else if (data.schedule_type === 5) {
                    this.form.schedule_month = Number(parts[0]) || 1;
                    this.form.schedule_day = Number(parts[1]) || 1;
                    this.form.schedule_value = parts[2] || '09:00';
                } else if (data.schedule_type === 1) {
                    this.scheduleMinutes = Number(parts[0]) || 30;
                }
                this.editorVisible = true;
            });
        },
        // 表单调度字段 → 存储 schedule_value
        buildScheduleValue() {
            switch (this.form.schedule_type) {
                case 1:
                    return String(this.scheduleMinutes || 30);
                case 3:
                    return `${this.form.schedule_week}|${this.form.schedule_value}`;
                case 4:
                    return `${this.form.schedule_day}|${this.form.schedule_value}`;
                case 5:
                    return `${this.form.schedule_month}|${this.form.schedule_day}|${this.form.schedule_value}`;
                default:
                    return this.form.schedule_value;
            }
        },
        saveTask() {
            if (!this.form.name.trim() || !this.form.instruction.trim()) {
                this.$Message.warning('请完整填写任务名称与任务指令');
                return;
            }
            if (this.form.notify_channels.indexOf('is_email') !== -1 && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(this.form.notify_email || '')) {
                this.$Message.warning('通知渠道含邮件时必须填写合法的收件邮箱');
                return;
            }
            const payload = { ...this.form, schedule_value: this.buildScheduleValue(), id: this.editId };
            this.saving = true;
            aiTaskSaveApi(payload)
                .then(() => {
                    this.$Message.success('保存成功');
                    this.editorVisible = false;
                    this.loadList();
                })
                .finally(() => {
                    this.saving = false;
                });
        },
        changeStatus(row) {
            aiTaskStatusApi(row.id, row.status)
                .then(() => {
                    this.$Message.success(row.status === 1 ? '已启用' : '已停用');
                    this.loadList();
                })
                .catch(() => {
                    row.status = row.status === 1 ? 0 : 1;
                });
        },
        // 测试运行：立即执行一次并打开运行历史查看结果
        triggerTask(row) {
            if (this.triggeringId) return;
            this.triggeringId = row.id;
            aiTaskTriggerApi(row.id)
                .then(() => {
                    this.$Message.success('已加入执行队列');
                    this.openRuns(row);
                })
                .catch(() => {})
                .finally(() => {
                    this.triggeringId = 0;
                });
        },
        removeTask(row) {
            this.$Modal.confirm({
                title: '提示',
                content: `确定删除任务「${row.name}」吗？其运行历史将一并删除。`,
                onOk: () => {
                    aiTaskDeleteApi(row.id).then(() => {
                        this.$Message.success('删除成功');
                        this.loadList();
                    });
                }
            });
        },
        /**
         * 打开运行历史（按任务维度）
         * @param {Object} row 任务行
         */
        openRuns(row) {
            this.currentTask = row;
            this.runsVisible = true;
            this.runsPage = 1;
            this.loadRuns();
        },
        loadRuns() {
            this.runsLoading = true;
            aiTaskRunsApi(this.currentTask.id, { page: this.runsPage, limit: this.runsLimit })
                .then((res) => {
                    const data = res.data || {};
                    this.runs = data.list || [];
                    this.runsTotal = data.count || 0;
                })
                .finally(() => {
                    this.runsLoading = false;
                });
        },
        /**
         * 打开运行详情（过程回放）
         * @param {Object} row 运行记录行
         */
        openRunDetail(row) {
            this.detailVisible = true;
            this.detailLoading = true;
            aiTaskRunDetailApi(row.id)
                .then((res) => {
                    this.runDetail = res.data || null;
                })
                .finally(() => {
                    this.detailLoading = false;
                });
        },
        /**
         * 工具消息结果摘要（详情回放用）
         * @param {Object} message tool 角色消息
         * @returns {String}
         */
        toolResultBrief(message) {
            try {
                const parsed = JSON.parse(message.content);
                if (parsed && parsed.error) return '调用失败：' + (parsed.message || '');
                let text = JSON.stringify(parsed);
                return text.length > 160 ? text.slice(0, 160) + '…' : text;
            } catch (e) {
                return String(message.content || '').slice(0, 160);
            }
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
.task-hero {
    display: flex;
    align-items: center;
    padding: 4px 0 20px;
}
.task-hero-icon {
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
.task-hero-main {
    flex: 1;
}
.task-hero-title {
    color: #1d2129;
    font-size: 17px;
    font-weight: 600;
}
.task-hero-sub {
    margin-top: 4px;
    color: #86909c;
    font-size: 13px;
}
/* 分组标题 */
.task-section {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}
.task-section-title {
    color: #1d2129;
    font-size: 15px;
    font-weight: 600;
}
.task-count-badge {
    margin-left: 8px;
    padding: 1px 9px;
    border-radius: 10px;
    background: #f2f3f5;
    color: #4e5969;
    font-size: 12px;
}
.task-enabled-count {
    margin-left: auto;
    color: #86909c;
    font-size: 13px;
}
/* 任务卡片 */
.task-list {
    min-height: 80px;
}
.task-card {
    margin-bottom: 12px;
    border: 1px solid #f0f1f3;
    border-radius: 12px;
    background: #fff;
    transition: box-shadow 0.2s;
}
.task-card:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}
.task-card-head {
    display: flex;
    align-items: center;
    padding: 14px 16px;
}
.task-avatar {
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
.task-meta {
    flex: 1;
    min-width: 0;
}
.task-name {
    display: flex;
    align-items: center;
}
.task-name-text {
    color: #1d2129;
    font-size: 14px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.task-tag {
    margin-left: 8px;
}
.task-sub {
    margin-top: 3px;
    color: #86909c;
    font-size: 12px;
}
.task-actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}
.task-action-icon {
    margin-left: 18px;
    color: #4e5969;
    font-size: 16px;
    cursor: pointer;
}
.task-action-icon:hover {
    color: #1d2129;
}
.task-action-icon.danger:hover {
    color: #f56c6c;
}
.task-action-icon.is-loading {
    animation: task-spin 1s linear infinite;
    pointer-events: none;
    opacity: 0.6;
}
.task-switch {
    margin-left: 18px;
}
@keyframes task-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
.task-card-body {
    padding: 0 16px 14px 64px;
}
.task-instruction {
    color: #4e5969;
    font-size: 13px;
    line-height: 1.7;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}
.task-channel-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
}
.channel-tag {
    margin-right: 0;
}
/* 编辑弹窗 */
.editor-tip {
    margin-bottom: 14px;
}
.schedule-input {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}
.schedule-field {
    width: 140px;
}
.schedule-month-field {
    width: 90px;
}
.schedule-day-field {
    width: 70px;
}
.task-field {
    width: 100%;
    max-width: 420px;
}
.email-field {
    margin-top: 8px;
}
.autonomy-tip {
    margin-top: 6px;
}
.advanced-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.advanced-field {
    width: 110px;
}
.task-pagination {
    margin-top: 14px;
    text-align: right;
}
/* 运行详情 */
.run-detail {
    max-height: 60vh;
    overflow-y: auto;
}
.run-detail-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.run-detail-error {
    margin-bottom: 12px;
    padding: 8px 12px;
    color: #f56c6c;
    font-size: 13px;
    background: #fef0f0;
    border-radius: 6px;
}
.run-messages {
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: #f7f8fa;
    border-radius: 8px;
    padding: 14px;
}
.msg-row.user {
    display: flex;
    justify-content: flex-end;
}
.msg-bubble.user {
    max-width: 70%;
    padding: 9px 14px;
    color: #303133;
    line-height: 1.65;
    white-space: pre-wrap;
    word-break: break-word;
    background: #f2f3f5;
    border-radius: 14px;
}
.msg-tool .tool-line {
    color: #86909c;
    font-size: 12px;
}
.msg-tool .tool-line i {
    margin-right: 4px;
}
.tool-result {
    margin-top: 4px;
    padding: 6px 10px;
    color: #4e5969;
    background: #fff;
    border: 1px solid #e5e6eb;
    border-radius: 6px;
    word-break: break-all;
}
.msg-content {
    color: #303133;
    font-size: 13px;
    line-height: 1.75;
    word-break: break-word;
}
.msg-content >>> p {
    margin: 0 0 8px;
}
.cell-ellipsis {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: bottom;
}
.cell-error {
    color: #f56c6c;
}
</style>

<style>
/* iView Table 的 render 函数内容不带 scoped 属性，且 Modal 会 transfer 到 body，
   表格内链接用无前缀专属类名全局定义（与 SkillManage 共用 .aiagent-link） */
</style>
