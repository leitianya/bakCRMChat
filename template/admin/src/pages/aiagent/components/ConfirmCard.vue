<template>
    <div class="confirm-row">
        <div :class="['confirm-card', message.status]">
            <div class="confirm-head">
                <Icon type="ios-alert-outline" />
                <span>AI 请求执行操作</span>
                <span v-if="message.status === 'approved'" class="confirm-state ok">已执行</span>
                <span v-else-if="message.status === 'rejected'" class="confirm-state">已取消</span>
                <span v-else-if="message.status === 'expired'" class="confirm-state">已过期</span>
            </div>
            <div class="confirm-tool">{{ cardTitle }}</div>
            <div v-if="cardParamList.length" class="confirm-args">
                <div v-for="(item, i) in cardParamList" :key="i" class="arg-row">
                    <span v-if="item.key" class="arg-key">{{ item.key }}：</span><span class="arg-value">{{ item.value }}</span>
                    <a v-if="item.long" class="arg-toggle" @click="toggleExpand(i)">{{ expanded[i] ? '收起' : '展开' }}</a>
                </div>
            </div>
            <div v-if="htmlContent" class="confirm-preview">
                <div class="preview-head" @click="previewOpen = !previewOpen">
                    <span>内容预览</span>
                    <Icon :type="previewOpen ? 'ios-arrow-up' : 'ios-arrow-down'" />
                </div>
                <iframe v-show="previewOpen" class="preview-frame" sandbox="" :srcdoc="htmlContent" frameborder="0"></iframe>
            </div>
            <div v-if="message.status === 'pending'" class="confirm-actions">
                <button class="confirm-btn cancel" type="button" @click="$emit('resolve', message, 'reject')">取消</button>
                <button class="confirm-btn ok" type="button" :disabled="chatting" @click="$emit('resolve', message, 'approve')">
                    确认执行
                </button>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * 工具执行确认卡片（样式对齐 crmeb_bz 原版 ConfirmCard）
 *
 * 展示模型提供的中文操作说明与参数摘要，长值可展开收起；
 * arguments.content 为 HTML（如邮件正文）时提供沙箱 iframe 渲染预览。
 */
export default {
    name: 'AiAgentConfirmCard',
    props: {
        // confirm 消息对象（display 承载模型提供的中文说明，arguments 为参数快照）
        message: {
            type: Object,
            required: true
        },
        // 是否有流式请求进行中（进行中禁止操作）
        chatting: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            // 参数值展示长度上限：超过则截断，提供展开/收起
            valueLimit: 100,
            // 已展开长值的下标集合
            expanded: {},
            // 内容预览默认展开：确认执行前用户最关心的就是内容长什么样
            previewOpen: true
        };
    },
    computed: {
        // 卡片标题：优先模型提供的中文说明，降级为工具名
        cardTitle() {
            return (this.message.display && this.message.display.title) || this.message.toolName || '操作确认';
        },
        // 卡片参数：优先模型提供的中文参数说明，降级为原始参数
        cardParams() {
            const params = this.message.display && this.message.display.params;
            return params && Object.keys(params).length ? params : this.message.arguments;
        },
        // 内容预览：arguments.content 为 HTML（富文本正文类工具，如发送邮件）时提供沙箱渲染预览
        htmlContent() {
            const args = this.message.arguments || {};
            const content = typeof args.content === 'string' ? args.content.trim() : '';
            return content.indexOf('<') === 0 ? content : '';
        },
        /**
         * 参数条目归一化：约定形态为 {参数名: 取值} 对象，每条渲染为「参数名：取值」单行；
         * 模型偶发把键值平铺成数组，此时按序两两配对兜底；
         * 超长取值截断展示；已提供渲染预览的 HTML 正文不再在参数区重复展示原文
         */
        cardParamList() {
            const raw = this.cardParams;
            const entries = Array.isArray(raw)
                ? Array.from({ length: Math.ceil(raw.length / 2) }, (_, i) => [raw[i * 2], raw[i * 2 + 1]])
                : Object.entries(raw || {});
            return entries
                .map(([key, value], i) => {
                    const text = this.formatValue(value);
                    const long = text.length > this.valueLimit;
                    return {
                        key: key === undefined || key === null ? '' : String(key),
                        full: text,
                        value: long && !this.expanded[i] ? text.slice(0, this.valueLimit) + '…' : text,
                        long
                    };
                })
                .filter((item) => item.key !== '' || item.full !== '')
                .filter((item) => this.htmlContent === '' || item.full.trim() !== this.htmlContent);
        }
    },
    methods: {
        /**
         * 切换长值展开/收起
         * @param {Number} index 参数下标
         */
        toggleExpand(index) {
            this.$set(this.expanded, index, !this.expanded[index]);
        },
        /**
         * 参数值展示：对象序列化为 JSON
         * @param {*} value 参数值
         * @returns {String}
         */
        formatValue(value) {
            if (value === null || value === undefined) return '';
            if (typeof value === 'object') return JSON.stringify(value);
            return String(value);
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
    color: #303133;
    background: #fdf6ec;
    border: 1px solid #faecd8;
    border-radius: 12px;
}
.confirm-card .confirm-head {
    color: #b88230;
}
.confirm-card.approved {
    background: #f0f9eb;
    border-color: #e1f3d8;
}
.confirm-card.approved .confirm-head {
    color: #67c23a;
}
.confirm-card.rejected,
.confirm-card.expired {
    color: #909399;
    background: #f4f4f5;
    border-color: #e9e9eb;
}
.confirm-card.rejected .confirm-head,
.confirm-card.expired .confirm-head {
    color: #909399;
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
.confirm-tool {
    margin-top: 10px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.6;
    word-break: break-word;
}
.confirm-args {
    margin-top: 8px;
}
.arg-row {
    margin-bottom: 4px;
    font-size: 13px;
    line-height: 1.7;
    word-break: break-all;
}
.arg-key {
    color: #909399;
}
.arg-value {
    color: #303133;
}
.arg-toggle {
    margin-left: 6px;
    color: #0256ff;
    font-size: 12px;
    cursor: pointer;
}
.confirm-preview {
    margin-top: 10px;
    border: 1px solid #faecd8;
    border-radius: 8px;
    overflow: hidden;
}
.preview-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    color: #b88230;
    font-size: 12px;
    cursor: pointer;
    user-select: none;
    background: rgba(255, 255, 255, 0.6);
}
.preview-frame {
    width: 100%;
    height: 260px;
    background: #fff;
    border: none;
    border-top: 1px solid #faecd8;
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
