<template>
    <div class="skill-manage">
        <div class="toolbar">
            <div>
                <div class="toolbar-title">技能文件</div>
                <div class="toolbar-tip">
                    Skill 以 Markdown 文件存储，Agent 对话时按选择注入上下文（Markdown 指南、流程说明等）。
                </div>
            </div>
            <Button type="primary" icon="md-add" @click="openEditor()">新建 Skill</Button>
        </div>

        <Table :columns="columns" :data="list" :loading="loading"></Table>

        <!-- Skill 编辑抽屉（右侧） -->
        <Drawer :title="editing ? '编辑 Skill' : '新建 Skill'" v-model="editorVisible" width="720" :mask-closable="false">
            <div class="drawer-body">
                <Form :model="form" :rules="rules" :label-width="90">
                    <Row :gutter="16">
                        <Col span="12">
                            <FormItem label="标识" prop="key">
                                <Input
                                    v-model.trim="form.key"
                                    :disabled="editing"
                                    :maxlength="64"
                                    placeholder="小写字母开头，可含数字、-、_"
                                ></Input>
                            </FormItem>
                        </Col>
                        <Col span="12">
                            <FormItem label="名称" prop="name">
                                <Input v-model.trim="form.name" :maxlength="100" placeholder="Skill 显示名称"></Input>
                            </FormItem>
                        </Col>
                    </Row>
                    <FormItem label="描述">
                        <Input v-model.trim="form.description" :maxlength="300" placeholder="一句话说明该 Skill 的用途"></Input>
                    </FormItem>
                    <FormItem label="正文" prop="content">
                        <Input
                            v-model="form.content"
                            type="textarea"
                            class="content-editor"
                            placeholder="Markdown 正文，将作为上下文注入选择了该 Skill 的对话"
                        ></Input>
                    </FormItem>
                    <FormItem label="状态">
                        <RadioGroup v-model="form.status">
                            <Radio :label="1">启用</Radio>
                            <Radio :label="0">停用</Radio>
                        </RadioGroup>
                    </FormItem>
                </Form>
            </div>
            <div class="demo-drawer-footer">
                <Button @click="editorVisible = false">取消</Button>
                <Button type="primary" :loading="saving" style="margin-left: 8px" @click="saveSkill">保存</Button>
            </div>
        </Drawer>

        <!-- 附属资料管理：技能目录下随 SKILL.md 存储的 .md 文件，Agent 经 crmeb_skill_load 按需加载 -->
        <Drawer :title="`附属资料 - ${currentSkill.name}`" v-model="attachmentsVisible" width="720" :mask-closable="false">
            <div class="drawer-body">
                <div class="attach-toolbar">
                    <div class="toolbar-tip attach-tip">
                        附属资料不随对话注入，Agent 判断需要更完整资料时按需加载；文件为 .md 格式（SKILL.md 与 EXAMPLES.md 为保留名）。
                    </div>
                    <Button type="primary" size="small" icon="md-add" @click="openAttachmentEditor()">新建附件</Button>
                </div>
                <Table :columns="attachColumns" :data="attachmentList" :loading="attachmentLoading" size="small"></Table>
            </div>
            <div class="demo-drawer-footer">
                <Button @click="attachmentsVisible = false">关闭</Button>
            </div>
        </Drawer>

        <!-- 附属资料编辑抽屉（右侧） -->
        <Drawer :title="attachmentEditing ? '编辑附件' : '新建附件'" v-model="attachmentEditorVisible" width="720" :mask-closable="false">
            <div class="drawer-body">
                <Form :model="attachmentForm" :rules="attachmentRules" :label-width="90">
                    <FormItem label="文件名" prop="file">
                        <Input
                            v-model.trim="attachmentForm.file"
                            :disabled="attachmentEditing"
                            :maxlength="100"
                            placeholder="如：功能索引（.md 后缀自动添加，不含路径分隔符）"
                        ></Input>
                    </FormItem>
                    <FormItem label="内容" prop="content">
                        <Input
                            v-model="attachmentForm.content"
                            type="textarea"
                            class="content-editor"
                            placeholder="Markdown 全文；Agent 加载后以原文为准"
                        ></Input>
                    </FormItem>
                </Form>
            </div>
            <div class="demo-drawer-footer">
                <Button @click="attachmentEditorVisible = false">取消</Button>
                <Button type="primary" :loading="attachmentSaving" style="margin-left: 8px" @click="saveAttachment">保存</Button>
            </div>
        </Drawer>
    </div>
</template>

<script>
/**
 * Skill 管理（样式对齐 crmeb_bz 原版）
 *
 * 技能文件表格 + 右侧抽屉编辑（标识/名称/描述/Markdown 正文/状态）
 * + 附属资料抽屉（列表 + 编辑抽屉）。与原版差异：AI 生成按钮暂未接
 * （原版依赖其自带 AI 写作弹窗组件，输出 HTML 不适合 Markdown 正文场景）。
 */
import {
    aiSkillListApi,
    aiSkillDetailApi,
    aiSkillSaveApi,
    aiSkillUpdateApi,
    aiSkillDeleteApi,
    aiSkillStatusApi,
    aiSkillAttachmentListApi,
    aiSkillAttachmentReadApi,
    aiSkillAttachmentSaveApi,
    aiSkillAttachmentDeleteApi
} from '@/api/aiagent';

export default {
    name: 'SkillManage',
    data() {
        return {
            loading: false,
            saving: false,
            editorVisible: false,
            editing: false,
            form: { key: '', name: '', description: '', content: '', status: 1 },
            rules: {
                key: [{ required: true, message: '请填写标识', trigger: 'blur' }],
                name: [{ required: true, message: '请填写名称', trigger: 'blur' }],
                content: [{ required: true, message: '请填写正文', trigger: 'blur' }]
            },
            currentSkill: { name: '' },
            attachmentsVisible: false,
            attachmentList: [],
            attachmentLoading: false,
            attachmentEditorVisible: false,
            attachmentEditing: false,
            attachmentSaving: false,
            attachmentForm: { file: '', content: '' },
            attachmentRules: {
                file: [{ required: true, message: '请填写文件名', trigger: 'blur' }],
                content: [{ required: true, message: '请填写内容', trigger: 'blur' }]
            },
            columns: [
                {
                    title: 'Skill',
                    key: 'name',
                    minWidth: 200,
                    render: (h, params) =>
                        h('div', [
                            h('div', { class: 'aiagent-cell-title' }, params.row.name),
                            h('div', { class: 'aiagent-cell-sub' }, params.row.key)
                        ])
                },
                {
                    title: '描述',
                    key: 'description',
                    minWidth: 260,
                    tooltip: true,
                    render: (h, params) => h('span', params.row.description || '暂无描述')
                },
                {
                    title: '更新时间',
                    key: 'update_time',
                    minWidth: 150,
                    render: (h, params) => h('span', this.formatTime(params.row.update_time))
                },
                {
                    title: '状态',
                    key: 'status',
                    width: 100,
                    render: (h, params) =>
                        h('i-switch', {
                            props: { value: params.row.status, trueValue: 1, falseValue: 0 },
                            on: {
                                input: (val) => {
                                    params.row.status = val;
                                },
                                'on-change': () => this.changeStatus(params.row)
                            }
                        })
                },
                {
                    title: '操作',
                    key: 'action',
                    width: 190,
                    render: (h, params) => {
                        const link = (text, cls, handler) =>
                            h('span', { class: ['aiagent-link', cls], on: { click: handler } }, text);
                        return h('div', [
                            link('编辑', '', () => this.openEditor(params.row)),
                            link('附件', '', () => this.openAttachments(params.row)),
                            link('删除', 'danger', () => this.removeSkill(params.row))
                        ]);
                    }
                }
            ],
            attachColumns: [
                {
                    title: '文件名',
                    key: 'file',
                    minWidth: 200,
                    render: (h, params) => h('div', { class: 'aiagent-cell-title aiagent-mono' }, params.row.file)
                },
                {
                    title: '标题',
                    key: 'title',
                    minWidth: 160,
                    tooltip: true,
                    render: (h, params) => h('span', params.row.title || '—')
                },
                {
                    title: '大小',
                    key: 'size',
                    width: 80,
                    render: (h, params) => h('span', this.formatSize(params.row.size))
                },
                {
                    title: '更新时间',
                    key: 'update_time',
                    minWidth: 130,
                    render: (h, params) => h('span', this.formatTime(params.row.update_time))
                },
                {
                    title: '操作',
                    key: 'action',
                    width: 110,
                    render: (h, params) => {
                        const link = (text, cls, handler) =>
                            h('span', { class: ['aiagent-link', cls], on: { click: handler } }, text);
                        return h('div', [
                            link('编辑', '', () => this.openAttachmentEditor(params.row)),
                            link('删除', 'danger', () => this.removeAttachment(params.row))
                        ]);
                    }
                }
            ]
        };
    },
    mounted() {
        this.loadList();
    },
    methods: {
        formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
        formatSize(size) {
            if (!size) return '—';
            return size < 1024 ? size + ' B' : (size / 1024).toFixed(1) + ' KB';
        },
        loadList() {
            this.loading = true;
            aiSkillListApi()
                .then((res) => {
                    this.list = res.data || [];
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        /**
         * 打开 Skill 编辑抽屉（无参为新建；编辑时拉详情回显正文）
         * @param {Object|undefined} row Skill 行
         */
        openEditor(row) {
            if (!row) {
                this.editing = false;
                this.form = { key: '', name: '', description: '', content: '', status: 1 };
                this.editorVisible = true;
                return;
            }
            aiSkillDetailApi(row.key).then((res) => {
                const data = res.data || {};
                this.editing = true;
                this.form = {
                    key: data.key,
                    name: data.name,
                    description: data.description,
                    content: data.content,
                    status: data.status
                };
                this.editorVisible = true;
            });
        },
        saveSkill() {
            if (!this.form.key.trim() || !this.form.name.trim() || !this.form.content.trim()) {
                this.$Message.warning('请完整填写标识、名称与正文');
                return;
            }
            this.saving = true;
            const request = this.editing ? aiSkillUpdateApi(this.form.key, this.form) : aiSkillSaveApi(this.form);
            request
                .then(() => {
                    this.$Message.success('保存成功');
                    this.editorVisible = false;
                    this.loadList();
                    this.$emit('changed');
                })
                .finally(() => {
                    this.saving = false;
                });
        },
        changeStatus(row) {
            aiSkillStatusApi(row.key, row.status)
                .then(() => {
                    this.$Message.success('修改成功');
                    this.loadList();
                    this.$emit('changed');
                })
                .catch(() => {
                    row.status = row.status === 1 ? 0 : 1;
                });
        },
        removeSkill(row) {
            this.$Modal.confirm({
                title: '提示',
                content: `确定删除 Skill「${row.name}」吗？其附属资料将一并删除。`,
                onOk: () => {
                    aiSkillDeleteApi(row.key).then(() => {
                        this.$Message.success('删除成功');
                        this.loadList();
                        this.$emit('changed');
                    });
                }
            });
        },
        /**
         * 打开附属资料抽屉
         * @param {Object} row Skill 行
         */
        openAttachments(row) {
            this.currentSkill = row;
            this.attachmentsVisible = true;
            this.loadAttachmentList();
        },
        loadAttachmentList() {
            this.attachmentLoading = true;
            aiSkillAttachmentListApi(this.currentSkill.key)
                .then((res) => {
                    this.attachmentList = res.data || [];
                })
                .finally(() => {
                    this.attachmentLoading = false;
                });
        },
        /**
         * 打开附属资料编辑抽屉（无参为新建；编辑时拉全文回显）
         * @param {Object|undefined} row 附件行
         */
        openAttachmentEditor(row) {
            if (!row) {
                this.attachmentEditing = false;
                this.attachmentForm = { file: '', content: '' };
                this.attachmentEditorVisible = true;
                return;
            }
            aiSkillAttachmentReadApi(this.currentSkill.key, row.file).then((res) => {
                const data = res.data || {};
                this.attachmentEditing = true;
                this.attachmentForm = { file: data.file, content: data.content };
                this.attachmentEditorVisible = true;
            });
        },
        saveAttachment() {
            let file = this.attachmentForm.file.trim();
            if (!file) {
                this.$Message.warning('请填写文件名');
                return;
            }
            if (!/\.md$/.test(file)) file += '.md';
            if (!this.attachmentForm.content.trim()) {
                this.$Message.warning('请填写内容');
                return;
            }
            this.attachmentSaving = true;
            aiSkillAttachmentSaveApi(this.currentSkill.key, { file, content: this.attachmentForm.content })
                .then(() => {
                    this.$Message.success('保存成功');
                    this.attachmentEditorVisible = false;
                    this.loadAttachmentList();
                })
                .finally(() => {
                    this.attachmentSaving = false;
                });
        },
        removeAttachment(row) {
            this.$Modal.confirm({
                title: '提示',
                content: `确定删除附件「${row.file}」吗？`,
                onOk: () => {
                    aiSkillAttachmentDeleteApi(this.currentSkill.key, row.file).then(() => {
                        this.$Message.success('删除成功');
                        this.loadAttachmentList();
                    });
                }
            });
        }
    }
};
</script>

<style scoped>
.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.toolbar-title {
    color: #303133;
    font-size: 18px;
    font-weight: 600;
}
.toolbar-tip,
.muted {
    margin-top: 6px;
    color: #909399;
    font-size: 13px;
}
.skill-name {
    color: #303133;
    font-weight: 600;
}
.mono {
    font-family: Menlo, Consolas, monospace;
    font-size: 12px;
}
/* 表格操作链接 */
.table-link {
    display: inline-block;
    margin-right: 14px;
    color: #0256ff;
    font-size: 13px;
    cursor: pointer;
}
.table-link:last-child {
    margin-right: 0;
}
.table-link:hover {
    opacity: 0.8;
}
.table-link.danger {
    color: #f56c6c;
}
.drawer-body {
    padding: 0 24px 10px;
}
.attach-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
}
.attach-tip {
    margin-top: 0;
    max-width: 440px;
}
</style>

<style>
/* iView Table 的 render 函数内容不带 scoped 属性，且 Drawer/Modal 会 transfer 到 body，
   表格内样式一律用无前缀专属类名全局定义（命名空间靠 aiagent- 前缀保证） */
.aiagent-link {
    display: inline-block;
    margin-right: 14px;
    color: #0256ff;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
    transition: opacity 0.15s, color 0.15s;
}
.aiagent-link:last-child {
    margin-right: 0;
}
.aiagent-link:hover {
    opacity: 0.8;
    color: #0246cc;
}
.aiagent-link.danger {
    color: #f56c6c;
}
.aiagent-link.danger:hover {
    color: #d9363e;
    opacity: 1;
}
.aiagent-cell-title {
    color: #303133;
    font-weight: 600;
}
.aiagent-cell-sub {
    margin-top: 4px;
    color: #909399;
    font-size: 12px;
    font-family: Menlo, Consolas, monospace;
}
.aiagent-mono {
    font-family: Menlo, Consolas, monospace;
    font-size: 12px;
}
</style>
<style>
/* Skill 正文编辑器（textarea 为 iView 内部元素，且 Drawer 默认 transfer 到 body，不加组件根前缀） */
.content-editor textarea {
    height: max(calc(100vh - 310px), 420px);
    resize: vertical;
    font-family: Menlo, Consolas, monospace;
    font-size: 13px;
    line-height: 1.7;
}
/* 抽屉底部操作条 */
.demo-drawer-footer {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 100%;
    padding: 10px 16px;
    text-align: right;
    background: #fff;
    border-top: 1px solid #e8eaec;
}
</style>
