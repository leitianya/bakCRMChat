<template>
    <div class="chat-input">
        <!-- 快捷场景标签：悬浮在输入框上方常显，点击回填输入框 -->
        <div class="input-topics">
            <button v-for="topic in topics" :key="topic.label" class="topic-tag" type="button" @click="fillTopic(topic)">
                <Icon :type="topic.icon" />
                <span>{{ topic.label }}</span>
            </button>
        </div>

        <!-- 输入卡片 -->
        <div class="input-card">
            <Input
                v-model="inputValue"
                type="textarea"
                :rows="2"
                :maxlength="4000"
                class="input-textarea"
                placeholder="输入问题，按 Enter 发送，Shift+Enter 换行"
                @on-keydown="handleEnterKey"
            ></Input>
            <div class="input-card-toolbar">
                <div class="toolbar-right">
                    <!-- 模型选择：弹出面板（当前模型名 + 列表勾选 + 配置入口） -->
                    <Poptip v-model="panelShow" trigger="click" placement="top-end" transfer :width="300">
                        <button class="model-trigger" type="button">
                            <Icon type="md-cube" />
                            <span>{{ currentModelName }}</span>
                            <Icon type="md-arrow-dropdown" />
                        </button>
                        <div slot="content" class="model-panel">
                            <div
                                v-for="model in models"
                                :key="model.id"
                                :class="['model-item', { active: modelId === model.id }]"
                                @click="selectModel(model)"
                            >
                                <span class="model-name">{{ model.name }}</span>
                                <Icon v-if="modelId === model.id" type="md-checkmark" />
                            </div>
                            <div class="model-item model-manage" @click="openManage">
                                <span class="model-name"><Icon type="md-create" /> 配置自定义模型</span>
                            </div>
                        </div>
                    </Poptip>
                    <!-- 发送 / 停止：流式输出进行中按钮变为红色停止态 -->
                    <button v-if="chatting" class="send-btn stop" type="button" @click="$emit('stop')">
                        <Icon type="md-pause" />
                    </button>
                    <button v-else class="send-btn" type="button" :disabled="!inputValue.trim()" @click="$emit('send')">
                        <Icon type="md-arrow-up" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * AI 对话输入区（样式对齐 crmeb_bz 原版）
 *
 * 快捷场景标签 + 圆角输入卡片 + 工具栏（模型弹出面板 / 圆形发送-停止按钮）。
 */
export default {
    name: 'AiAgentChatInput',
    props: {
        // 快捷场景标签（label/icon/prompt）
        topics: {
            type: Array,
            default: () => []
        },
        // 可选模型列表（id/name）
        models: {
            type: Array,
            default: () => []
        },
        // 当前模型 ID
        modelId: {
            type: Number,
            default: 0
        },
        // 是否有流式请求进行中（进行中禁止发送）
        chatting: {
            type: Boolean,
            default: false
        },
        // 输入框内容（v-model 双向绑定，真实状态由父层持有）
        value: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            panelShow: false
        };
    },
    computed: {
        inputValue: {
            get() {
                return this.value;
            },
            set(value) {
                this.$emit('input', value);
            }
        },
        currentModelName() {
            const model = this.models.find((item) => item.id === this.modelId);
            return model ? model.name : '一号通AI';
        }
    },
    methods: {
        /**
         * 回车发送 / Shift+回车换行（中文输入法组词期间的回车不触发；流式进行中回车不发送）
         * 注意：iView 的 on-keydown 转发的是所有按键事件，必须先过滤出回车键，
         * 否则打字/标点的每次 keydown 都会误触发发送；
         * 组词期间的 keydown keyCode 恒为 229（Safari 的 isComposing 在组词中恒为 false，
         * WebKit 已知问题），不会命中回车过滤，天然不发送
         * @param {KeyboardEvent} event 键盘事件
         */
        handleEnterKey(event) {
            // 只处理回车键（兼容个别环境 key 缺失，以 keyCode 13 兜底）
            if (event.key !== 'Enter' && event.keyCode !== 13) {
                return;
            }
            if (event.shiftKey || event.isComposing || event.keyCode === 229 || this.chatting) {
                return;
            }
            event.preventDefault();
            this.$emit('send');
        },
        /**
         * 切换模型
         * @param {Object} model 模型项
         */
        selectModel(model) {
            this.panelShow = false;
            this.$emit('select-model', model);
        },
        // 打开模型管理遮盖层
        openManage() {
            this.panelShow = false;
            this.$emit('manage', 'model');
        },
        /**
         * 快捷场景标签：仅把预设问题回填输入框并聚焦，不自动发送
         * @param {Object} topic 场景项
         */
        fillTopic(topic) {
            this.inputValue = topic.prompt;
            this.focus();
        },
        // 聚焦输入框并把光标移到末尾（供父级编辑回填后调用）
        focus() {
            this.$nextTick(() => {
                const textarea = this.$el.querySelector('textarea');
                if (textarea) {
                    textarea.focus();
                    textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                }
            });
        }
    }
};
</script>

<style scoped>
/* 根容器：宽屏时限宽居中，与消息区 max-width 一致；32px 水平内边距在此统一承载 */
.chat-input {
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    padding: 0 32px;
}
/* 快捷场景标签：悬浮输入框上方常显 */
.input-topics {
    display: flex;
    gap: 10px;
    margin: 0 0 10px;
    flex-wrap: wrap;
}
.topic-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    color: #606266;
    font-size: 13px;
    cursor: pointer;
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 999px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.topic-tag i {
    font-size: 14px;
}
.topic-tag:hover {
    color: #0256ff;
    background: #f0f7ff;
    border-color: #0256ff;
}
/* 输入卡片 */
.input-card {
    margin: 0 0 24px;
    border: 1px solid #e4e7ed;
    border-radius: 20px;
    transition: border-color 0.2s;
}
.input-card:focus-within {
    border-color: #0256ff;
}
.input-card >>> .ivu-input {
    padding: 16px 18px 0;
    color: #303133;
    font-size: 14px;
    background: transparent;
    border: none;
    box-shadow: none;
    resize: none;
}
.input-card-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 8px 14px 12px;
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.model-trigger {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 220px;
    padding: 6px 10px;
    color: #303133;
    font-size: 13px;
    cursor: pointer;
    background: transparent;
    border: none;
    border-radius: 8px;
}
.model-trigger:hover {
    color: #0256ff;
    background: #f2f3f5;
}
.model-trigger span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.send-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    color: #fff;
    font-size: 17px;
    cursor: pointer;
    background: #0256ff;
    border: none;
    border-radius: 50%;
    transition: opacity 0.2s;
}
.send-btn:hover:not(:disabled) {
    opacity: 0.85;
}
.send-btn:disabled {
    color: #a8abb2;
    cursor: not-allowed;
    background: #e9e9eb;
}
/* 停止态：流式输出进行中，点击终止输出 */
.send-btn.stop {
    cursor: pointer;
    background: #f56c6c;
}
/* 模型弹出面板（Poptip transfer 渲染在 body，需非 scoped 样式，见下方全局块） */
.model-panel {
    max-height: 320px;
    overflow-y: auto;
}
.model-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 10px;
    cursor: pointer;
    color: #303133;
    border-radius: 6px;
}
.model-item:hover {
    background: #f5f7fa;
}
.model-item.active {
    color: #0256ff;
    background: #f0f7ff;
}
.model-item.active i {
    color: #0256ff;
}
.model-name {
    overflow: hidden;
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.model-manage {
    color: #606266;
    border-top: 1px solid #ebeef5;
    border-radius: 0;
}
.model-manage:hover {
    color: #0256ff;
}
</style>

<style>
/* Poptip transfer 到 body 的弹出层内容（无法 scoped 命中，全局命名空间防冲突） */
.ivu-poptip-popper .model-panel .model-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 10px;
    cursor: pointer;
    color: #303133;
    border-radius: 6px;
}
.ivu-poptip-popper .model-panel .model-item:hover {
    background: #f5f7fa;
}
.ivu-poptip-popper .model-panel .model-item.active {
    color: #0256ff;
    background: #f0f7ff;
}
.ivu-poptip-popper .model-panel .model-name {
    overflow: hidden;
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ivu-poptip-popper .model-panel .model-manage {
    color: #606266;
    border-top: 1px solid #ebeef5;
    border-radius: 0;
}
.ivu-poptip-popper .model-panel .model-manage:hover {
    color: #0256ff;
    background: #f5f7fa;
}
</style>
