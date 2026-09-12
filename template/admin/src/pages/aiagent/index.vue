<template>
    <div class="aiagent-page">
        <div class="chat-layout">
            <!-- 左侧：导航 + 会话列表 -->
            <ConversationPanel
                :conversations="conversations"
                :active-id="conversationId"
                @create="newConversation"
                @select="selectConversation"
                @remove="removeConversation"
                @pin="pinConversation"
                @rename="renameConversation"
                @nav="openManage"
            />

            <!-- 右侧：消息区 + 输入区（管理页遮盖层覆盖于此） -->
            <section class="chat-main">
                <div v-if="activeManage" class="manage-overlay">
                    <div class="manage-header">
                        <button class="manage-back" type="button" @click="closeManage">
                            <Icon type="ios-arrow-back" />
                            <span>返回</span>
                        </button>
                        <span class="manage-title">{{ manageTitles[activeManage] }}</span>
                    </div>
                    <div class="manage-body">
                        <ModelManage v-if="activeManage === 'model'" @changed="loadOptions" />
                        <McpManage v-else-if="activeManage === 'mcp'" @changed="loadOptions" />
                        <SkillManage v-else-if="activeManage === 'skill'" @changed="loadOptions" />
                        <TaskManage v-else-if="activeManage === 'task'" />
                    </div>
                </div>

                <div ref="messageBox" class="message-list">
                    <div v-if="!messages.length" class="empty-chat">
                        <div class="empty-title">今天帮你做些什么？</div>
                        <div class="empty-tip">点击输入框右下角模型名称可切换 AI 模型</div>
                    </div>

                    <template v-for="(message, index) in messages">
                        <!-- 用户消息：右侧浅灰气泡，悬停浮现复制/编辑 -->
                        <div v-if="message.role === 'user'" :key="'u' + index" class="user-row">
                            <div class="user-msg">
                                <div class="user-bubble">{{ message.content }}</div>
                                <div class="user-actions">
                                    <Tooltip content="复制" transfer>
                                        <Icon type="md-copy" @click.native="copyMessage(message.content)" />
                                    </Tooltip>
                                    <Tooltip content="编辑" transfer>
                                        <Icon type="md-create" @click.native="editMessage(message.content)" />
                                    </Tooltip>
                                </div>
                            </div>
                        </div>

                        <!-- 问询卡片：AI 发起单选/多选/文本提问 -->
                        <AskCard
                            v-else-if="message.role === 'confirm' && message.interaction === 'ask_user'"
                            :key="'c' + index"
                            :message="message"
                            :chatting="chatting"
                            @resolve="resolveConfirm"
                        />

                        <!-- 执行确认卡片：需确认工具被拦截，等待用户决定 -->
                        <ConfirmCard
                            v-else-if="message.role === 'confirm'"
                            :key="'c' + index"
                            :message="message"
                            :chatting="chatting"
                            @resolve="resolveConfirm"
                        />

                        <!-- AI 回复：通栏排版 -->
                        <div v-else :key="'a' + index" class="assistant-row">
                            <!-- 深度思考：思维链、调用过的 Skill 与工具痕迹，默认折叠 -->
                            <div
                                v-if="message.reasoning || (message.skills && message.skills.length) || (message.tool_calls && message.tool_calls.length)"
                                class="reasoning-block"
                            >
                                <div class="reasoning-toggle" @click="message.reasoningOpen = !message.reasoningOpen">
                                    <Icon :type="message.reasoningOpen ? 'ios-arrow-down' : 'ios-arrow-forward'" />
                                    <span>深度思考</span>
                                </div>
                                <div v-show="message.reasoningOpen" class="reasoning-content">
                                    <!-- trimEnd：思维链文本尾部换行经 pre-wrap 渲染成大片空白，去掉尾部空白再显示 -->
                                    {{ (message.reasoning || '').replace(/\s+$/, '') }}
                                    <div v-if="message.skills && message.skills.length" class="skill-trace">
                                        <span
                                            v-for="(skill, i) in message.skills"
                                            :key="'s' + i"
                                            class="trace-tag"
                                        >Skill {{ skill.name || skill }}</span>
                                    </div>
                                    <div v-if="message.tool_calls && message.tool_calls.length" class="skill-trace">
                                        <span v-for="(tool, i) in message.tool_calls" :key="'t' + i" class="trace-tag">调用 {{ tool.name }}</span>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="message.content"
                                class="assistant-content"
                                :class="{ typing: message.streaming && !message.content }"
                                v-html="renderMarkdown(message.content)"
                            ></div>
                            <div v-else-if="message.streaming" class="assistant-content typing">
                                <Icon type="ios-loading" class="spin" /> 正在思考…
                            </div>
                            <!-- 消息底部操作区：复制 / 重新生成（仅最后一条回复）/ 回复时间 -->
                            <div v-if="message.content && !message.streaming" class="assistant-actions">
                                <Tooltip content="复制" transfer>
                                    <Icon type="md-copy" class="action-icon" @click.native="copyMessage(message.content)" />
                                </Tooltip>
                                <Tooltip v-if="isLastAssistant(message)" content="重新生成" transfer>
                                    <Icon type="md-refresh" class="action-icon" @click.native="regenerateMessage(message)" />
                                </Tooltip>
                                <span v-if="message.create_time" class="action-time">{{ formatTime(message.create_time) }}</span>
                            </div>
                            <!-- 推荐追问：回答完成后展示，点击直接发送 -->
                            <div v-if="!message.streaming && message.followups && message.followups.length" class="followups">
                                <button
                                    v-for="question in message.followups"
                                    :key="question"
                                    class="followup-tag"
                                    type="button"
                                    :disabled="chatting"
                                    @click="sendMessage(question)"
                                >{{ question }}</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- 输入区：快捷场景标签 + 输入卡片 -->
                <ChatInput
                    ref="chatInput"
                    v-model="chatInput"
                    :topics="topics"
                    :models="options.models"
                    :model-id="modelId"
                    :chatting="chatting"
                    @send="sendMessage()"
                    @stop="stopStreaming"
                    @select-model="selectModel"
                    @manage="openManage"
                />
            </section>
        </div>
    </div>
</template>

<script>
/**
 * AI Agent 对话工作台（样式与交互对齐 crmeb_bz 原版）
 *
 * 左侧管理入口与会话列表，右侧对话区（SSE 流式输出、深度思考折叠、
 * 确认/问询卡片、推荐追问、复制/重新生成）；模型/MCP/Skill/任务管理以遮盖层在本页内打开。
 */
import ConversationPanel from './components/ConversationPanel';
import ChatInput from './components/ChatInput';
import AskCard from './components/AskCard';
import ConfirmCard from './components/ConfirmCard';
import ModelManage from './components/ModelManage';
import McpManage from './components/McpManage';
import SkillManage from './components/SkillManage';
import TaskManage from './components/TaskManage';
import { mdToHtml } from './markdown';
import {
    aiAgentOptionsApi,
    aiAgentConversationsApi,
    aiAgentMessagesApi,
    aiAgentDeleteConversationApi,
    aiAgentUpdateConversationApi,
    aiAgentChatStreamApi,
    aiAgentConfirmStreamApi
} from '@/api/aiagent';

export default {
    name: 'AiAgentIndex',
    components: {
        ConversationPanel,
        ChatInput,
        AskCard,
        ConfirmCard,
        ModelManage,
        McpManage,
        SkillManage,
        TaskManage
    },
    data() {
        return {
            // 会话与消息状态
            conversations: [],
            conversationId: 0,
            messages: [],
            chatInput: '',
            modelId: 0,
            options: { models: [], mcp_servers: [], skills: [] },
            // 快捷场景标签：点击仅回填输入框
            topics: [
                { label: '系统设置', icon: 'md-settings', prompt: '帮我分析当前系统设置，有哪些配置值得优化？' },
                { label: '用户分析', icon: 'md-person', prompt: '帮我分析平台的用户数据：用户规模、增长趋势与活跃情况' },
                { label: '客服分析', icon: 'md-headset', prompt: '帮我分析近期客服接待情况：会话量、响应效率与服务质量' },
                { label: '技能能力', icon: 'md-compass', prompt: '你有哪些能力和技能？可以帮我做什么？' }
            ],
            // 管理遮盖层：模型 / MCP / Skill / 自动化任务
            activeManage: '',
            manageTitles: { model: '模型管理', mcp: 'MCP 管理', skill: 'Skill 管理', task: '自动化任务' },
            // 流式请求状态
            chatting: false,
            ctrl: null
        };
    },
    mounted() {
        this.loadOptions(true);
        this.loadConversations(true);
    },
    beforeDestroy() {
        this.stopStreaming();
    },
    methods: {
        renderMarkdown(content) {
            return mdToHtml(content);
        },
        /**
         * 回复时间格式化：当天/昨天/前天用相对描述，本年省略年份
         * @param {Number} timestamp 秒级时间戳
         * @returns {String}
         */
        formatTime(timestamp) {
            const time = Number(timestamp);
            if (!time) return '';
            const date = new Date(time * 1000);
            if (Number.isNaN(date.getTime())) return '';
            const pad = (n) => String(n).padStart(2, '0');
            const hhmm = `${pad(date.getHours())}:${pad(date.getMinutes())}`;
            // 以自然日零点为界计算相差天数，避免"不足 24 小时也算昨天"的歧义
            const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
            const dayDiff = Math.round((startOfDay(new Date()) - startOfDay(date)) / 86400000);
            const labels = ['今天', '昨天', '前天'];
            if (dayDiff >= 0 && dayDiff < labels.length) return `${labels[dayDiff]} ${hhmm}`;
            const md = `${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
            if (date.getFullYear() === new Date().getFullYear()) return `${md} ${hhmm}`;
            return `${date.getFullYear()}-${md} ${hhmm}`;
        },
        /**
         * 创建一条流式回复占位
         * @returns {Object}
         */
        newReply() {
            return {
                role: 'assistant',
                content: '',
                reasoning: '',
                reasoningOpen: false,
                skills: [],
                tool_calls: [],
                followups: [],
                streaming: true
            };
        },
        /**
         * 是否为最后一条 AI 回复（重新生成仅对最后一条回复开放）
         * @param {Object} message AI 回复
         * @returns {Boolean}
         */
        isLastAssistant(message) {
            const list = this.messages;
            return list[list.length - 1] === message;
        },
        /* ---------------- 数据加载 ---------------- */

        /**
         * 加载对话配置选项（模型/连接器/Skill）
         * @param {Boolean} applyDefault 首次加载时选中默认模型（is_default 全局唯一：登记默认或一号通）
         */
        loadOptions(applyDefault = false) {
            aiAgentOptionsApi().then((res) => {
                this.options = Object.assign({}, this.options, res.data || {});
                if (applyDefault) {
                    const def = (this.options.models || []).find((model) => model.is_default === 1);
                    if (def) this.modelId = def.id;
                }
            });
        },
        /**
         * 加载会话列表
         * @param {Boolean} autoSelect 进入页面时自动选中最近一次会话
         */
        loadConversations(autoSelect = false) {
            aiAgentConversationsApi().then((res) => {
                this.conversations = res.data || [];
                // 默认加载最后一次对话（会话列表已按更新时间倒序）
                if (autoSelect && !this.conversationId && this.conversations.length) {
                    this.selectConversation(this.conversations[0]);
                }
            });
        },
        /**
         * 加载会话消息（过滤中间过程消息，仅展示问答内容与交互卡片）
         * @param {Number} conversationId 会话 ID
         */
        loadMessages(conversationId) {
            aiAgentMessagesApi(conversationId).then((res) => {
                this.messages = (res.data || [])
                    .filter((message) => message.role !== 'tool' && (message.role === 'confirm' || !message.tool_name))
                    .map((message) => {
                        // 交互卡片：tool_payload 承载参数快照、交互类型与确认状态
                        if (message.role === 'confirm') {
                            const payload = message.tool_payload || {};
                            return {
                                role: 'confirm',
                                id: message.id,
                                messageId: message.id,
                                toolName: message.tool_name,
                                arguments: payload.arguments || {},
                                interaction: payload.interaction || 'execute',
                                display: payload.display || null,
                                status: payload.status || 'pending'
                            };
                        }
                        return Object.assign({}, message, {
                            reasoning: (message.tool_payload || {}).reasoning || '',
                            reasoningOpen: false,
                            skills: (message.tool_payload || {}).skills || [],
                            followups: (message.tool_payload || {}).followups || [],
                            // 历史消息回显工具调用标签（只展示工具名）
                            tool_calls: ((message.tool_payload || {}).tool_calls || [])
                                .map((item) => ({ name: (item && item.name) || '' }))
                                .filter((item) => item.name)
                        });
                    });
                this.scrollToBottom(true);
            });
        },
        /* ---------------- 会话操作 ---------------- */

        // 新建会话：从管理遮盖层发起时需关闭遮盖层回到对话区
        newConversation() {
            this.stopStreaming();
            this.activeManage = '';
            this.conversationId = 0;
            this.messages = [];
            this.chatInput = '';
        },
        /**
         * 选中会话：从管理页点击会话记录时关闭遮盖层展示对话内容
         * @param {Object} item 会话项
         */
        selectConversation(item) {
            this.activeManage = '';
            if (this.conversationId === item.id) return;
            this.stopStreaming();
            this.conversationId = item.id;
            this.loadMessages(item.id);
        },
        /**
         * 删除会话
         * @param {Object} item 会话项
         */
        removeConversation(item) {
            this.$Modal.confirm({
                title: '提示',
                content: '确定删除该会话吗？',
                onOk: () => {
                    aiAgentDeleteConversationApi(item.id).then(() => {
                        this.$Message.success('删除成功');
                        if (this.conversationId === item.id) this.newConversation();
                        this.loadConversations();
                    });
                }
            });
        },
        /**
         * 置顶/取消置顶会话
         * @param {Object} item 会话项
         */
        pinConversation(item) {
            const pin = item.is_pin === 1 ? 0 : 1;
            aiAgentUpdateConversationApi(item.id, { is_pin: pin }).then(() => {
                this.$Message.success(pin ? '已置顶' : '已取消置顶');
                this.loadConversations();
            });
        },
        /**
         * 重命名会话标题
         * @param {Object} item 会话项
         */
        renameConversation(item) {
            const self = this;
            this.renameTitle = item.title;
            this.$Modal.confirm({
                render: (h) => h('Input', {
                    props: { value: self.renameTitle, autofocus: true, maxlength: 40 },
                    on: {
                        input: (val) => {
                            self.renameTitle = val;
                        }
                    }
                }),
                title: '重命名会话',
                onOk: () => {
                    const title = (self.renameTitle || '').trim();
                    if (!title) {
                        self.$Message.error('标题不能为空');
                        return;
                    }
                    aiAgentUpdateConversationApi(item.id, { title }).then(() => {
                        self.$Message.success('修改成功');
                        self.loadConversations();
                    });
                }
            });
        },
        /* ---------------- 管理遮盖层 ---------------- */

        /**
         * 打开管理遮盖层
         * @param {String} target 面板标识（model/mcp/skill/task）
         */
        openManage(target) {
            if (typeof target !== 'string') target = 'model';
            this.activeManage = target;
        },
        // 关闭管理遮盖层（管理页可能改了模型/连接器配置，刷新选项）
        closeManage() {
            this.activeManage = '';
            this.loadOptions();
        },
        /* ---------------- 对话 ---------------- */

        /**
         * 切换当前对话使用的模型
         * @param {Object} model 模型项
         */
        selectModel(model) {
            this.modelId = model.id;
        },
        /**
         * 发送消息：不传参从输入框取内容并发送后清空；传入字符串（推荐追问/编辑回填）直接作为消息发送
         * @param {String|undefined} question 可选的待发送问题
         */
        sendMessage(question) {
            const fromInput = typeof question !== 'string';
            const content = (fromInput ? this.chatInput : question).trim();
            if (!content || this.chatting) return;
            if (fromInput) {
                this.chatInput = '';
            }
            this.messages.push({ role: 'user', content });
            this.startStream(content);
        },
        /**
         * 重新生成：取该回复前最近的用户消息原样重发，移除旧回复后原地重新流式生成
         * @param {Object} message 待重新生成的 AI 回复
         */
        regenerateMessage(message) {
            if (this.chatting || !this.isLastAssistant(message)) return;
            const list = this.messages;
            const index = list.indexOf(message);
            let question = '';
            for (let i = index - 1; i >= 0; i--) {
                if (list[i].role === 'user') {
                    question = list[i].content;
                    break;
                }
            }
            if (!question) return;
            list.splice(index, 1);
            this.startStream(question);
        },
        /**
         * 发起流式对话：创建回复占位并建立 SSE 连接（重新生成复用）
         * @param {String} content 用户消息
         */
        startStream(content) {
            this.chatting = true;
            const reply = this.newReply();
            this.messages.push(reply);
            this.scrollToBottom(true);
            const ctrl = new AbortController();
            this.ctrl = ctrl;
            const done = () => {
                this.ctrl = null;
                reply.streaming = false;
                this.loadConversations();
            };
            aiAgentChatStreamApi(
                {
                    conversation_id: this.conversationId,
                    message: content,
                    model_id: this.modelId
                },
                (event) => this.handleStreamEvent(event, reply),
                { signal: ctrl.signal }
            )
                .then(() => {
                    done();
                    // 拉取服务端持久化后的完整记录（含消息 ID、时间与卡片状态）
                    this.loadMessages(this.conversationId);
                })
                .catch((e) => {
                    this.ctrl = null;
                    if (e && e.name === 'AbortError') {
                        // 手动终止：保留已输出内容，正文为空时补占位提示
                        this.finishAborted(reply);
                    } else {
                        this.$Message.error((e && e.message) || '对话失败');
                        reply.streaming = false;
                        // 会话已创建但流失败：刷新以显示服务端已落库内容
                        if (this.conversationId) this.loadMessages(this.conversationId);
                    }
                    this.chatting = false;
                    this.loadConversations();
                });
        },
        /**
         * SSE 事件分发：推理/正文增量、替换、工具痕迹、卡片、推荐追问与结束
         * @param {Object} event SSE 事件载荷
         * @param {Object} reply 当前流式回复占位
         */
        handleStreamEvent(event, reply) {
            switch (event.event) {
                case 'start':
                    // 会话就绪：记录后端会话 ID（新对话首条消息场景）
                    if (event.conversation_id) this.conversationId = event.conversation_id;
                    break;
                case 'reasoning':
                    reply.reasoning += event.text || '';
                    this.scrollToBottom();
                    break;
                case 'delta':
                    reply.content += event.text || '';
                    this.scrollToBottom();
                    break;
                case 'replace':
                    reply.content = event.content || '';
                    if (event.reasoning) reply.reasoning = event.reasoning;
                    break;
                case 'skills':
                    reply.skills = event.skills || [];
                    break;
                case 'tool':
                    // 工具调用前的正文片段属于前言，转入思考区展示
                    if (reply.content) {
                        reply.reasoning += (reply.reasoning ? '\n' : '') + reply.content;
                        reply.content = '';
                    }
                    reply.tool_calls.push({
                        name: event.name,
                        arguments: event.arguments,
                        result: event.result
                    });
                    this.scrollToBottom();
                    break;
                case 'confirm':
                    // 交互卡片已落库：结束本轮流式并刷新消息展示卡片
                    reply.streaming = false;
                    this.chatting = false;
                    if (this.conversationId) this.loadMessages(this.conversationId);
                    break;
                case 'followups':
                    reply.followups = event.questions || [];
                    break;
                case 'done':
                    reply.tool_calls = (event.tool_calls || []).length ? event.tool_calls : reply.tool_calls;
                    this.chatting = false;
                    break;
                case 'error':
                    this.$Message.error(event.msg || 'AI 请求失败');
                    reply.streaming = false;
                    this.chatting = false;
                    if (this.conversationId) this.loadMessages(this.conversationId);
                    break;
            }
        },
        /**
         * 手动终止后的收尾：保留已输出内容，正文为空时补占位提示
         * @param {Object} reply 流式回复占位
         */
        finishAborted(reply) {
            if (!reply.content) {
                reply.content = '已手动终止本次输出。';
            }
            reply.streaming = false;
            this.chatting = false;
        },
        // 手动终止当前流式输出（保留已生成内容，后端随连接断开停止生成）
        stopStreaming() {
            if (this.ctrl) {
                this.ctrl.abort();
                this.ctrl = null;
            }
            this.chatting = false;
        },
        /**
         * 用户处理交互卡片：execute 类确认执行/取消；ask_user 类提交答案/跳过（结果以流式响应继续渲染）
         * @param {Object} message 卡片消息
         * @param {String} action approve | reject
         * @param {Object} extra ask_user 场景附加（answers/custom）
         */
        resolveConfirm(message, action, extra = {}) {
            if (this.chatting || message.status !== 'pending') return;
            this.chatting = true;
            // 乐观更新卡片状态，失败时回滚
            message.status = action === 'approve' ? 'approved' : 'rejected';
            const reply = this.newReply();
            this.messages.push(reply);
            this.scrollToBottom(true);
            const ctrl = new AbortController();
            this.ctrl = ctrl;
            aiAgentConfirmStreamApi(
                {
                    conversation_id: this.conversationId,
                    message_id: message.messageId,
                    action,
                    answers: extra.answers || [],
                    custom: extra.custom || ''
                },
                (event) => this.handleStreamEvent(event, reply),
                { signal: ctrl.signal }
            )
                .then(() => {
                    this.ctrl = null;
                    reply.streaming = false;
                    this.chatting = false;
                    this.loadConversations();
                })
                .catch((e) => {
                    this.ctrl = null;
                    if (e && e.name === 'AbortError') {
                        // 手动终止：卡片决定已提交，保持当前状态防止重复执行
                        this.finishAborted(reply);
                    } else {
                        this.$Message.error((e && e.message) || '操作失败');
                        message.status = 'pending';
                        reply.streaming = false;
                        this.chatting = false;
                    }
                });
        },
        /**
         * 编辑消息：内容回填输入框并聚焦
         * @param {String} content 用户消息内容
         */
        editMessage(content) {
            this.chatInput = content;
            this.$refs.chatInput && this.$refs.chatInput.focus();
        },
        /**
         * 复制消息内容到剪贴板
         * @param {String} content 消息内容
         */
        copyMessage(content) {
            const textarea = document.createElement('textarea');
            textarea.value = content;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                this.$Message.success('已复制');
            } catch (e) {
                this.$Message.error('复制失败');
            }
            document.body.removeChild(textarea);
        },
        /* ---------------- 滚动 ---------------- */

        /**
         * 消息区滚动到底部
         * @param {Boolean} force 强制滚动（切换会话/新消息时）
         */
        scrollToBottom(force = false) {
            if (!force && this.scrollPaused) return;
            this.$nextTick(() => {
                const box = this.$refs.messageBox;
                if (box) box.scrollTop = box.scrollHeight;
            });
        }
    }
};
</script>

<style scoped>
.aiagent-page {
    padding-bottom: 20px;
}
.chat-layout {
    display: flex;
    height: calc(100vh - 110px);
    min-height: 480px;
    margin-top: 0;
    overflow: hidden;
    border: 1px solid #ebeef5;
    border-radius: 12px;
}
.chat-main {
    position: relative;
    display: flex;
    flex: 1;
    min-width: 0;
    flex-direction: column;
    background: #fff;
}
/* 管理页遮盖层：覆盖右侧对话区，左侧导航仍可切换 */
.manage-overlay {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 20;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
}
.manage-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
}
.manage-back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    color: #606266;
    font-size: 13px;
    cursor: pointer;
    background: #f4f4f5;
    border: none;
    border-radius: 16px;
}
.manage-back:hover {
    color: #0256ff;
    background: #e9e9eb;
}
.manage-title {
    color: #303133;
    font-size: 15px;
    font-weight: 600;
}
.manage-body {
    flex: 1;
    padding: 0 20px 20px;
    overflow-y: auto;
}
.message-list {
    /* flex:1 纵向撑满使输入区贴底；宽屏时水平限宽居中，避免通栏排版被拉得过长 */
    flex: 1;
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    padding: 24px 32px;
    overflow-y: auto;
}
.empty-chat {
    margin-top: 22vh;
    text-align: center;
}
.empty-title {
    color: #303133;
    font-size: 22px;
    font-weight: 600;
}
.empty-tip {
    margin-top: 10px;
    color: #a8abb2;
    font-size: 13px;
}
/* 用户消息：右侧浅灰气泡 */
.user-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 26px;
}
.user-msg {
    display: flex;
    max-width: 68%;
    flex-direction: column;
    align-items: flex-end;
}
.user-bubble {
    padding: 12px 18px;
    color: #303133;
    line-height: 1.65;
    white-space: pre-wrap;
    word-break: break-word;
    background: #f2f3f5;
    border-radius: 18px;
}
/* 气泡下方操作图标：hover 时浮现 */
.user-actions {
    display: flex;
    gap: 14px;
    margin-top: 6px;
    padding-right: 6px;
    color: #a8abb2;
    font-size: 14px;
    opacity: 0;
    transition: opacity 0.15s;
}
.user-actions i {
    cursor: pointer;
}
.user-actions i:hover {
    color: #0256ff;
}
.user-row:hover .user-actions {
    opacity: 1;
}
/* AI 回复：通栏排版 */
.assistant-row {
    margin-bottom: 30px;
}
/* 深度思考折叠块：小字切换 + 左侧竖线引用样式 */
.reasoning-block {
    margin-bottom: 12px;
}
.reasoning-toggle {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    color: #9aa0a8;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
}
.reasoning-toggle i {
    font-size: 12px;
}
.reasoning-toggle:hover {
    color: #606266;
}
.reasoning-content {
    margin-top: 6px;
    padding: 4px 0 4px 16px;
    color: #9aa0a8;
    font-size: 13px;
    line-height: 1.9;
    white-space: pre-wrap;
    word-break: break-word;
    border-left: 3px solid #e3e5e8;
}
/* 系统过程内调用的 Skill 标签：与思维链文本区分 */
.skill-trace {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}
/* 过程痕迹信息签（Skill / 工具调用）：纯展示，浅灰禁用态避免误导可点击 */
.trace-tag {
    display: inline-block;
    height: 20px;
    line-height: 18px;
    padding: 0 6px;
    font-size: 12px;
    color: #909399;
    background: #f7f8fa;
    border: 1px solid #e9e9eb;
    border-radius: 4px;
    cursor: default;
    user-select: none;
}
.assistant-content {
    color: #303133;
    font-size: 14px;
    line-height: 1.75;
    word-break: break-word;
}
.assistant-content >>> p {
    margin: 0 0 12px;
}
.assistant-content >>> p:last-child {
    margin-bottom: 0;
}
.assistant-content >>> h1,
.assistant-content >>> h2,
.assistant-content >>> h3,
.assistant-content >>> h4,
.assistant-content >>> h5,
.assistant-content >>> h6 {
    margin: 20px 0 10px;
    color: #1f2937;
    line-height: 1.4;
}
.assistant-content >>> hr {
    height: 0;
    margin: 16px 0;
    border: 0;
    border-top: 1px solid #e4e7ed;
}
.assistant-content >>> ul,
.assistant-content >>> ol {
    margin: 0 0 12px;
    padding-left: 24px;
}
.assistant-content >>> blockquote {
    margin: 12px 0;
    padding: 4px 12px;
    color: #606266;
    border-left: 3px solid #dcdfe6;
}
.assistant-content >>> pre {
    margin: 12px 0;
    padding: 12px;
    overflow-x: auto;
    color: #303133;
    white-space: pre-wrap;
    background: #f5f7fa;
    border-radius: 6px;
}
.assistant-content >>> code {
    padding: 2px 4px;
    font-family: Consolas, Monaco, monospace;
    font-size: 0.9em;
    background: #f5f7fa;
    border-radius: 3px;
}
.assistant-content >>> pre code {
    padding: 0;
    background: transparent;
}
.assistant-content >>> table {
    width: 100%;
    margin: 12px 0;
    border-collapse: collapse;
}
.assistant-content >>> th,
.assistant-content >>> td {
    padding: 8px 10px;
    text-align: left;
    border: 1px solid #ebeef5;
}
.assistant-content >>> th {
    background: #f5f7fa;
}
.assistant-content >>> a {
    color: #0256ff;
}
.assistant-content.typing {
    color: #909399;
}
.assistant-content .spin {
    animation: aiagent-spin 1s linear infinite;
}
@keyframes aiagent-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
/* 推荐追问：回答完成后展示的胶囊标签，点击直接发送 */
.followups {
    display: flex;
    gap: 8px;
    margin-top: 14px;
    flex-wrap: wrap;
}
.followup-tag {
    padding: 6px 14px;
    color: #606266;
    font-size: 13px;
    line-height: 1.5;
    text-align: left;
    cursor: pointer;
    word-break: break-all;
    background: #f7f8fa;
    border: 1px solid #e4e7ed;
    border-radius: 999px;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.followup-tag:hover:not(:disabled) {
    color: #0256ff;
    background: #f0f7ff;
    border-color: #0256ff;
}
.followup-tag:disabled {
    color: #c0c4cc;
    cursor: not-allowed;
}
/* 消息底部操作区：完成后的复制与重新生成图标 */
.assistant-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 12px;
    color: #a8abb2;
    font-size: 14px;
}
.action-icon {
    cursor: pointer;
    transition: color 0.15s;
}
.action-icon:hover {
    color: #0256ff;
}
/* 回复完成时间：弱化展示，与操作图标同行对齐 */
.action-time {
    font-size: 12px;
    color: #c0c4cc;
}
</style>
