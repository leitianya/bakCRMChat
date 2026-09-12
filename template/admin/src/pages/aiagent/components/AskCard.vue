<template>
    <div class="confirm-row">
        <div :class="['confirm-card', 'ask', message.status]">
            <div class="confirm-head">
                <Icon type="ios-chatbubbles-outline" />
                <span>AI 需要你确认</span>
                <span v-if="message.status === 'approved'" class="confirm-state ok">已回答</span>
                <span v-else-if="message.status === 'rejected'" class="confirm-state">已跳过</span>
                <span v-else-if="message.status === 'expired'" class="confirm-state">已过期</span>
            </div>
            <div class="ask-question">{{ form.question }}</div>
            <template v-if="form.type !== 'text'">
                <div class="ask-options">
                    <div
                        v-for="option in form.options"
                        :key="option.value"
                        :class="['ask-option', { selected: isSelected(option.value) }]"
                        @click="toggleOption(option.value)"
                    >
                        <span :class="['ask-mark', form.type]">
                            <Icon v-if="isSelected(option.value)" type="md-checkmark" />
                        </span>
                        <span class="ask-label">{{ option.label }}</span>
                    </div>
                    <div v-if="form.allowCustom" :class="['ask-option', { selected: useCustom }]" @click="toggleCustom">
                        <span :class="['ask-mark', form.type]">
                            <Icon v-if="useCustom" type="md-checkmark" />
                        </span>
                        <span class="ask-label">其他（自定义输入）</span>
                    </div>
                </div>
                <div v-if="useCustom" class="ask-custom">
                    <Input v-model="customText" type="textarea" :rows="2" :maxlength="500" placeholder="请输入你的想法"></Input>
                </div>
            </template>
            <div v-else class="ask-custom">
                <Input v-model="customText" type="textarea" :rows="2" :maxlength="500" placeholder="请输入"></Input>
            </div>
            <div v-if="message.status === 'pending'" class="confirm-actions">
                <button class="confirm-btn cancel" type="button" @click="$emit('resolve', message, 'reject')">跳过</button>
                <button class="confirm-btn ok" type="button" :disabled="chatting || !answerReady" @click="submit">提交</button>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * AI 问询卡片（样式对齐 crmeb_bz 原版 AskCard）
 *
 * AI 发起的单选/多选/文本提问；交互态组件内自持，提交后经 confirm_stream 续跑。
 */
export default {
    name: 'AiAgentAskCard',
    props: {
        // confirm 消息对象（arguments 承载问询参数快照，status 为确认状态）
        message: {
            type: Object,
            required: true
        },
        // 是否有流式请求进行中（进行中禁止提交）
        chatting: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            selected: [],
            useCustom: false,
            customText: ''
        };
    },
    computed: {
        // 问询参数规范化：options 兼容字符串与 {value,label} 两种形态
        form() {
            const args = this.message.arguments || {};
            const options = (args.options || [])
                .map((option) => {
                    if (option && typeof option === 'object') {
                        return { value: String(option.value || ''), label: String(option.label || option.value || '') };
                    }
                    return { value: String(option), label: String(option) };
                })
                .filter((option) => option.value !== '');
            return {
                question: String(args.question || ''),
                type: ['single', 'multi', 'text'].indexOf(args.type) !== -1 ? args.type : 'single',
                options,
                allowCustom: args.allow_custom !== false
            };
        },
        // 单选需选中一项；多选/文本始终可提交
        answerReady() {
            if (this.form.type === 'single') {
                return this.selected.length > 0 || (this.useCustom && !!this.customText.trim());
            }
            return true;
        }
    },
    methods: {
        isSelected(value) {
            return this.selected.indexOf(value) !== -1;
        },
        /**
         * 切换选项选中态：single 单选互斥（与自定义输入互斥），multi 多选
         * @param {String} value 选项标识
         */
        toggleOption(value) {
            if (this.message.status !== 'pending') return;
            if (this.form.type === 'single') {
                this.selected = this.selected.indexOf(value) !== -1 ? [] : [value];
                this.useCustom = false;
            } else {
                const index = this.selected.indexOf(value);
                if (index === -1) {
                    this.selected.push(value);
                } else {
                    this.selected.splice(index, 1);
                }
            }
        },
        // 切换自定义输入：选中时清空选项（单选语义）
        toggleCustom() {
            if (this.message.status !== 'pending') return;
            this.useCustom = !this.useCustom;
            if (this.useCustom && this.form.type === 'single') {
                this.selected = [];
            }
        },
        // 提交作答：自定义输入并入 answers（value 为空、label 取输入文本）
        submit() {
            const answers = [...this.selected];
            if (this.useCustom && this.customText.trim()) {
                answers.push(this.customText.trim());
            }
            this.$emit('resolve', this.message, 'approve', { answers, custom: this.customText });
        }
    }
};
</script>

<style scoped>
.confirm-row {
    display: flex;
    justify-content: center;
    margin-bottom: 30px;
}
.confirm-card {
    width: 380px;
    padding: 14px 16px;
    background: #f0f7ff;
    border: 1px solid #d9ecff;
    border-radius: 12px;
}
.confirm-card .confirm-head {
    color: #0256ff;
}
.confirm-card.approved .confirm-head {
    color: #67c23a;
}
.confirm-card.rejected .confirm-head,
.confirm-card.expired .confirm-head {
    color: #909399;
}
.confirm-card.rejected,
.confirm-card.expired {
    color: #909399;
    background: #f4f4f5;
    border-color: #e9e9eb;
}
.confirm-card.rejected .ask-question,
.confirm-card.expired .ask-question,
.confirm-card.rejected .ask-label,
.confirm-card.expired .ask-label {
    color: #909399;
}
.confirm-card.rejected .ask-option,
.confirm-card.expired .ask-option {
    cursor: default;
    border-color: #e9e9eb;
}
.confirm-head {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
}
.confirm-state {
    margin-left: auto;
    color: #909399;
    font-size: 12px;
    font-weight: 400;
}
.confirm-state.ok {
    color: #67c23a;
}
.ask-question {
    margin-top: 10px;
    color: #303133;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.6;
    word-break: break-word;
}
.ask-options {
    display: flex;
    margin-top: 10px;
    flex-direction: column;
    gap: 8px;
}
.ask-option {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 12px;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    transition: border-color 0.15s, background 0.15s;
}
.ask-option:hover {
    border-color: #0256ff;
}
.ask-option.selected {
    color: #0256ff;
    background: #f0f7ff;
    border-color: #0256ff;
}
.ask-mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    margin-top: 1px;
    color: #fff;
    font-size: 11px;
    background: #fff;
    border: 1px solid #c0c4cc;
}
.ask-mark.single {
    border-radius: 50%;
}
.ask-mark.multi {
    border-radius: 4px;
}
.ask-option.selected .ask-mark {
    color: #fff;
    background: #0256ff;
    border-color: #0256ff;
}
.ask-label {
    color: #303133;
    line-height: 1.5;
    word-break: break-word;
}
.ask-custom {
    margin-top: 10px;
}
.confirm-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 12px;
}
.confirm-btn {
    padding: 5px 16px;
    font-size: 12px;
    cursor: pointer;
    border-radius: 14px;
    border: 1px solid transparent;
}
.confirm-btn.cancel {
    color: #606266;
    background: #fff;
    border-color: #dcdfe6;
}
.confirm-btn.cancel:hover {
    color: #909399;
    border-color: #c0c4cc;
}
.confirm-btn.ok {
    color: #fff;
    background: #0256ff;
}
.confirm-btn.ok:hover:not(:disabled) {
    opacity: 0.85;
}
.confirm-btn.ok:disabled {
    cursor: not-allowed;
    background: #a0cfff;
}
</style>
