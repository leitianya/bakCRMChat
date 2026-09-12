<template>
    <div class="wang-editor-wrap">
        <div class="wang-editor">
            <Toolbar class="editor-toolbar" :editor="editor" :defaultConfig="toolbarConfig" mode="default"/>
            <Editor class="editor-content" v-model="valueHtml" :defaultConfig="editorConfig" mode="default"
                    :style="{ height: height + 'px', overflowY: 'hidden' }"
                    @onCreated="handleCreated"
                    @onChange="handleChange"/>
        </div>

        <!-- 图片素材库弹窗（多选，替代 v5 内置 base64 直插） -->
        <Modal v-model="modalPic" width="950" title="上传图片" footer-hide :mask-closable="false">
            <uploadPictures v-if="modalPic" :gridBtn="gridBtn" :gridPic="gridPic" isChoice="多选"
                            @getPicD="getPic"></uploadPictures>
        </Modal>

        <!-- 源码（HTML）视图弹窗 -->
        <Modal v-model="modalHtml" width="860" title="HTML 源码" :mask-closable="false" @on-ok="applyHtml">
            <Input v-model="htmlSource" type="textarea" :rows="18" class="html-source"></Input>
        </Modal>

        <!-- AI 写作弹窗（公共组件） -->
        <AiModal ref="aiModal"
                 :ai-default-prompt="aiDefaultPrompt"
                 :ai-default-system-prompt="aiDefaultSystemPrompt"
                 :ai-show-system-prompt="aiShowSystemPrompt"
                 @insert="insertAiContent"/>
    </div>
</template>

<script>
/**
 * wangEditor 富文本编辑器（v5，参考 crmeb_bz 项目同名组件移植，Vue 2 + iView 适配版）
 *
 * 用法：<wang-editor v-model="content" :height="500"></wang-editor>
 * 工具栏在 v5 内置菜单基础上追加自定义菜单：HTML 源码（alertHtml）、
 * 图片素材库（uploadImgMenuKey）、AI 写作（aiMenuKey）。
 */
import '@wangeditor/editor/dist/css/style.css';
import { Editor, Toolbar } from '@wangeditor/editor-for-vue';
import AiModal from './AiModal.vue';
import uploadPictures from '@/components/uploadPictures';
import bus from './bus';
import './registerMenus';

export default {
    name: 'WangEditor',
    components: { Editor, Toolbar, AiModal, uploadPictures },
    props: {
        // 编辑内容（HTML），支持 v-model
        value: {
            type: String,
            default: ''
        },
        // 编辑区高度（px）
        height: {
            type: Number,
            default: 500
        },
        // AI 写作默认问题（写作提示词输入框默认值），不传时使用组件内置默认
        aiDefaultPrompt: {
            type: String,
            default: ''
        },
        // AI 写作默认系统提示词，不传时使用组件内置默认
        aiDefaultSystemPrompt: {
            type: String,
            default: ''
        },
        // 是否显示系统提示词编辑区（默认隐藏，隐藏时仍按默认值生效）
        aiShowSystemPrompt: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            editor: null,
            valueHtml: '<p><br></p>',
            htmlSource: '',
            modalPic: false,
            modalHtml: false,
            gridPic: {
                xl: 6,
                lg: 8,
                md: 12,
                sm: 12,
                xs: 12
            },
            gridBtn: {
                xl: 4,
                lg: 8,
                md: 8,
                sm: 8,
                xs: 8
            },
            // 自定义菜单键 -> 视图切换（v5 内置菜单键见官方文档）
            toolbarConfig: {
                toolbarKeys: [
                    'alertHtml',
                    'headerSelect',
                    'bold',
                    'fontSize',
                    'fontFamily',
                    'italic',
                    'underline',
                    'through', // 删除线
                    'indent', // 首行缩进
                    'delIndent',
                    'lineHeight',
                    'color',
                    'bgColor',
                    'insertLink',
                    'bulletedList',
                    'numberedList',
                    'justifyLeft',
                    'justifyRight',
                    'justifyCenter',
                    'justifyJustify',
                    'blockquote',
                    'emotion',
                    'uploadImgMenuKey', // 图片：打开项目素材库选择弹窗
                    'codeBlock',
                    'divider',
                    'aiMenuKey' // AI 写作
                ]
            },
            // 小图转 base64 直插，不走服务端（对齐参考项目）
            editorConfig: {
                placeholder: '',
                readOnly: false,
                autoFocus: false,
                scroll: true,
                MENU_CONF: {
                    uploadImage: {
                        base64LimitSize: 5 * 1024 * 1024
                    }
                }
            }
        };
    },
    watch: {
        // 外部传入内容变化时同步进编辑器（比较旧值防止回写覆盖用户编辑）
        value(val) {
            if (val && this.editor && val !== this.editor.getHtml() && val !== this.valueHtml) {
                this.valueHtml = val;
                this.$nextTick(() => {
                    if (this.editor && !this.editor.isDestroyed) this.editor.setHtml(val);
                });
            }
        }
    },
    mounted() {
        if (this.value) this.valueHtml = this.value;
        // 订阅自定义菜单的总线事件（回调具名，配合 beforeDestroy 解绑）
        bus.$on('UploadImg', this.onUploadImgEvent);
        bus.$on('Html', this.onHtmlEvent);
        bus.$on('AiWrite', this.onAiWriteEvent);
    },
    beforeDestroy() {
        // 总线回调需具名，off 才能正确解绑
        bus.$off('UploadImg', this.onUploadImgEvent);
        bus.$off('Html', this.onHtmlEvent);
        bus.$off('AiWrite', this.onAiWriteEvent);
        const editor = this.editor;
        if (editor) editor.destroy();
    },
    methods: {
        // 判断总线事件是否属于当前编辑器实例（同页多实例防串扰）
        isCurrentEditor(editor) {
            return editor === this.editor;
        },
        onUploadImgEvent(editor) {
            if (!this.isCurrentEditor(editor)) return;
            this.modalPic = true;
        },
        onHtmlEvent(editor) {
            if (!this.isCurrentEditor(editor)) return;
            this.htmlSource = this.valueHtml;
            this.modalHtml = true;
        },
        onAiWriteEvent(editor) {
            if (!this.isCurrentEditor(editor)) return;
            this.$refs.aiModal.open();
        },
        handleCreated(editor) {
            this.editor = editor;
            if (this.value && this.value !== editor.getHtml()) {
                editor.setHtml(this.value);
            }
        },
        handleChange(editor) {
            const html = editor.getHtml();
            this.$emit('input', html);
            this.$emit('editorContent', html);
        },
        // 素材库多选回调：批量插入图片
        getPic(pc) {
            this.modalPic = false;
            const html = pc
                .map((item) => `<img src="${item.att_dir}" style="max-width:100%;"/>`)
                .join('');
            this.insertHtmlToEditor(html);
        },
        // 源码视图确定：写回编辑器
        applyHtml() {
            if (this.editor && !this.editor.isDestroyed && this.htmlSource) {
                this.editor.setHtml(this.htmlSource);
            }
        },
        // AI 生成内容插入编辑器（AiModal 回调；以当前内容为基准追加到末尾）
        insertAiContent(html) {
            if (this.editor && !this.editor.isDestroyed && html) {
                this.editor.setHtml(this.editor.getHtml() + html);
            }
        },
        insertHtmlToEditor(html) {
            const editor = this.editor;
            if (!editor || editor.isDestroyed || !html) return;
            this.$nextTick(() => {
                if (editor.isDestroyed) return;
                // 从未聚焦过编辑器时 selection 为空，先聚焦到文末再插入
                if (!editor.selection) {
                    editor.focus(true);
                }
                editor.restoreSelection();
                editor.dangerouslyInsertHtml(html);
            });
        }
    }
};
</script>

<style scoped>
.wang-editor-wrap {
    width: 90%;
}
.wang-editor {
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    overflow: hidden;
    background-color: #fff;
    z-index: 100;
}
.editor-toolbar {
    border-bottom: 1px solid #dcdfe6;
}
.html-source {
    font-family: monospace;
}
</style>
