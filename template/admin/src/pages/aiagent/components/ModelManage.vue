<template>
    <div class="model-manage">
        <div class="toolbar">
            <div>
                <div class="toolbar-title">模型登记</div>
                <div class="toolbar-tip">
                    配置 AI 对话所使用的模型：填写接口地址与密钥即可接入 DeepSeek、通义、Kimi 等厂商，同协议厂商无需重复开发。
                </div>
            </div>
            <Button type="primary" icon="md-add" @click="openEditor()">新增模型</Button>
        </div>

        <div class="filter-bar">
            <Input
                v-model.trim="filter.keyword"
                placeholder="名称 / 模型 ID"
                clearable
                style="width: 220px"
                @on-enter="search"
                @on-clear="search"
            ></Input>
            <Select v-model="filter.protocol" placeholder="协议" clearable style="width: 180px; margin-left: 10px" @on-change="search">
                <Option v-for="p in protocols" :key="p.protocol" :label="p.name" :value="p.protocol"></Option>
            </Select>
            <Select v-model="filter.status" placeholder="状态" clearable style="width: 120px; margin-left: 10px" @on-change="search">
                <Option label="启用" :value="1"></Option>
                <Option label="停用" :value="0"></Option>
            </Select>
            <Button type="primary" ghost style="margin-left: 10px" @click="search">查询</Button>
        </div>

        <Table :columns="columns" :data="tableRows" :loading="loading" class="mt16"></Table>

        <div class="pager">
            <span class="pager-total">共 {{ count }} 条</span>
            <Page :total="count" :current="page" :page-size="limit" @on-change="changePage"></Page>
        </div>

        <!-- 新增/编辑模型弹窗（label 置顶 + 高级配置折叠，样式对齐 crmeb_bz 原版） -->
        <Modal v-model="editorVisible" :title="form.id ? '编辑 AI 模型' : '新增 AI 模型'" width="640" :mask-closable="false">
            <Form ref="modelForm" :model="form" :rules="rules" label-position="top">
                <FormItem prop="base_url">
                    <div class="base-url-head">
                        <span class="base-url-label">自定义请求地址<i class="required-star">*</i></span>
                        <span class="base-url-switch">
                            <Icon type="md-link" />
                            <span>完整 URL</span>
                            <i-switch v-model="form.full_url" :true-value="1" :false-value="0" size="small"></i-switch>
                        </span>
                    </div>
                    <div class="field-tip">
                        {{
                            form.full_url === 1
                                ? '请填写完整请求 URL，请求将直接使用此 URL，不拼接路径。'
                                : '请填写兼容 OpenAI API 的服务端点地址，不要以斜杠结尾。/chat/completions 将会被补全到你填写的地址末尾。'
                        }}
                    </div>
                    <Input
                        v-model.trim="form.base_url"
                        :maxlength="255"
                        :placeholder="form.full_url === 1 ? '例如 https://api.openai.com/v1/chat/completions' : '例如 https://api.openai.com/v1'"
                    ></Input>
                </FormItem>
                <FormItem label="模型 ID" prop="model">
                    <Input v-model.trim="form.model" :maxlength="100" placeholder="输入模型 ID，例如 deepseek-chat、qwen-plus"></Input>
                </FormItem>
                <FormItem label="模型名称" prop="name">
                    <Input
                        v-model.trim="form.name"
                        :maxlength="32"
                        show-word-limit
                        placeholder="请输入模型名称"
                        @on-input="nameTouched = true"
                    ></Input>
                    <div class="field-tip">在模型列表中展示的名称，未设置时默认显示模型 ID。</div>
                </FormItem>
                <FormItem label="API 密钥">
                    <Input
                        v-model.trim="form.api_key"
                        :maxlength="255"
                        type="password"
                        password
                        :placeholder="form.id ? '留空保持原密钥不变' : '厂商控制台签发的 API Key'"
                    ></Input>
                </FormItem>
                <Collapse v-model="advancedOpen" class="advanced-collapse">
                    <Panel name="advanced">高级配置
                        <div slot="content">
                            <FormItem label="模型能力">
                                <Checkbox v-model="form.supportsToolsBool">支持工具调用</Checkbox>
                                <Checkbox v-model="form.supportsVisionBool">支持视觉输入</Checkbox>
                            </FormItem>
                            <FormItem label="采样温度">
                                <InputNumber v-model="form.temperature" :min="0" :max="2" :step="0.1"></InputNumber>
                            </FormItem>
                            <FormItem label="最大输出 Token">
                                <InputNumber v-model="form.max_tokens" :min="1" :max="128000" :step="256"></InputNumber>
                                <span class="field-tip inline-tip">0 表示使用服务端默认值</span>
                            </FormItem>
                            <FormItem label="额外请求参数">
                                <Input
                                    v-model.trim="form.extra_params"
                                    type="textarea"
                                    :rows="2"
                                    :maxlength="2000"
                                    show-word-limit
                                    placeholder='JSON 对象，原样合入请求体。如 qwen3 系列关闭思考：{"enable_thinking": false}'
                                ></Input>
                                <span class="field-tip inline-tip">厂商私有参数透传，model/messages/stream 不可覆盖</span>
                            </FormItem>
                            <FormItem label="上下文窗口">
                                <InputNumber v-model="form.context_window" :min="0" :max="10000000" :step="1000"></InputNumber>
                                <span class="field-tip inline-tip">仅用于展示，0 表示未填写</span>
                            </FormItem>
                            <FormItem label="排序">
                                <InputNumber v-model="form.sort" :min="0" :max="9999"></InputNumber>
                                <span class="field-tip inline-tip">数值越大越靠前</span>
                            </FormItem>
                            <FormItem label="选项">
                                <Checkbox v-model="form.isDefaultBool">设为系统默认模型</Checkbox>
                                <RadioGroup v-model="form.status" style="margin-left: 24px">
                                    <Radio :label="1">启用</Radio>
                                    <Radio :label="0">停用</Radio>
                                </RadioGroup>
                            </FormItem>
                        </div>
                    </Panel>
                </Collapse>
            </Form>
            <div slot="footer">
                <div class="dialog-footer">
                    <span class="footer-tip"><Icon type="ios-information-circle-outline" /> 连通性测试会发起一次真实请求，会消耗少量模型Token</span>
                    <span>
                        <Button @click="resetForm">重置</Button>
                        <Button type="primary" :loading="!!savingPhase" @click="saveModel">{{ savingText }}</Button>
                    </span>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script>
/**
 * AI 模型管理（样式对齐 crmeb_bz 原版）
 *
 * 列表首行为内置一号通虚拟模型（不可编辑删除，可设为默认）；
 * 保存前先做连通性测试，测试通过才落库；高级配置折叠承载能力/温度/Token 等参数。
 */
import {
    aiModelListApi,
    aiModelProtocolsApi,
    aiModelYihaotongStatusApi,
    aiModelDetailApi,
    aiModelSaveApi,
    aiModelUpdateApi,
    aiModelDeleteApi,
    aiModelStatusApi,
    aiModelSetDefaultApi,
    aiModelTestApi
} from '@/api/aiagent';

const emptyForm = () => ({
    id: 0,
    name: '',
    protocol: 'openai_compatible',
    provider: '',
    model: '',
    base_url: '',
    api_key: '',
    full_url: 0,
    supports_tools: 0,
    supports_vision: 0,
    context_window: 0,
    max_tokens: 2048,
    temperature: 0.7,
    extra_params: '',
    is_default: 0,
    sort: 0,
    status: 1,
    // 弹窗内布尔镜像（iView Checkbox 双绑布尔），保存时回写数值字段
    supportsToolsBool: false,
    supportsVisionBool: false,
    isDefaultBool: false
});

export default {
    name: 'ModelManage',
    data() {
        return {
            loading: false,
            // 保存按钮阶段：''=空闲，test=连通测试进行中，save=落库进行中
            savingPhase: '',
            // 一号通凭证配置状态：1=已配置 0=未配置（未配置时虚拟行显示一键配置入口）
            yihaotongConfigured: 1,
            editorVisible: false,
            list: [],
            count: 0,
            page: 1,
            limit: 20,
            filter: { keyword: '', protocol: '', status: '' },
            protocols: [],
            form: emptyForm(),
            advancedOpen: [],
            nameTouched: false,
            columns: [
                {
                    title: '模型',
                    key: 'name',
                    minWidth: 200,
                    render: (h, params) => {
                        const row = params.row;
                        const children = [
                            h('div', { class: 'model-name' }, [
                                row.name,
                                row.is_default === 1
                                    ? h('Tag', { props: { size: 'small', color: 'warning' }, class: 'def-tag' }, '默认')
                                    : null
                            ]),
                            h('div', { class: 'muted' }, row.model || '')
                        ];
                        return h('div', children);
                    }
                },
                {
                    title: '接口地址',
                    key: 'base_url',
                    minWidth: 200,
                    tooltip: true,
                    render: (h, params) => h('span', params.row.base_url || '-')
                },
                {
                    title: '密钥',
                    key: 'has_api_key',
                    minWidth: 140,
                    render: (h, params) => {
                        if (params.row.virtual) {
                            // 一号通未配置时提示去一键配置
                            return this.yihaotongConfigured === 1
                                ? h('span', { class: 'muted' }, '复用一号通凭证')
                                : h('span', { class: 'yihaotong-missing' }, '未配置，请一键配置');
                        }
                        return h('span', { class: 'mono' }, params.row.has_api_key ? params.row.api_key : '未配置');
                    }
                },
                {
                    title: '能力',
                    key: 'supports_tools',
                    minWidth: 130,
                    render: (h, params) => {
                        const row = params.row;
                        const tags = [];
                        if (row.supports_tools === 1) tags.push(h('Tag', { props: { size: 'small', color: 'success' } }, '工具调用'));
                        if (row.supports_vision === 1) tags.push(h('Tag', { props: { size: 'small', color: 'default' } }, '视觉'));
                        if (row.supports_tools !== 1 && row.supports_vision !== 1) tags.push(h('span', { class: 'muted' }, '-'));
                        return h('div', tags);
                    }
                },
                {
                    title: '状态',
                    key: 'status',
                    width: 100,
                    render: (h, params) => {
                        if (params.row.virtual) return h('span', { class: 'muted' }, '-');
                        return h('i-switch', {
                            props: { value: params.row.status, trueValue: 1, falseValue: 0, size: 'default' },
                            on: {
                                input: (val) => {
                                    params.row.status = val;
                                },
                                'on-change': () => this.changeStatus(params.row)
                            }
                        });
                    }
                },
                {
                    title: '操作',
                    key: 'action',
                    width: 200,
                    render: (h, params) => {
                        const row = params.row;
                        const link = (text, cls, handler) =>
                            h('span', { class: ['table-link', cls], on: { click: handler } }, text);
                        if (row.virtual) {
                            // 一号通未配置：操作列引导一键配置（跳转一号通设置页，登录后凭证自动写入）
                            if (this.yihaotongConfigured !== 1) {
                                return h('div', [link('一键配置', '', () => this.goYihaotong())]);
                            }
                            return h('div', [
                                row.is_default === 1
                                    ? link('内置模型', 'disabled', () => {})
                                    : link('设为默认', '', () => this.setDefault(row))
                            ]);
                        }
                        return h('div', [
                            link(
                                '设为默认',
                                row.is_default === 1 || row.status !== 1 ? 'disabled' : '',
                                () => this.setDefault(row)
                            ),
                            link('编辑', '', () => this.openEditor(row)),
                            link('删除', 'danger', () => this.removeModel(row))
                        ]);
                    }
                }
            ],
            rules: {
                model: [{ required: true, message: '请填写模型 ID', trigger: 'blur' }],
                base_url: [
                    {
                        required: true,
                        validator: (rule, value, callback) => {
                            if (!/^https?:\/\//i.test(value || '')) {
                                callback(new Error('接口地址必须以 http:// 或 https:// 开头'));
                            } else {
                                callback();
                            }
                        },
                        trigger: 'blur'
                    }
                ]
            }
        };
    },
    computed: {
        // 保存按钮文案随阶段切换
        savingText() {
            if (!this.savingPhase) return this.form.id ? '保存' : '添加模型';
            return this.savingPhase === 'test' ? '连通测试中' : '保存中';
        },
        // 列表行 = 内置一号通模型（固定首行，不可编辑删除） + 登记模型
        tableRows() {
            // 系统默认唯一：存在登记的默认模型时，一号通行不再标记默认
            const hasRegisteredDefault = this.list.some((row) => row.is_default === 1);
            return [
                {
                    virtual: true,
                    id: 0,
                    name: hasRegisteredDefault ? '一号通AI' : '一号通AI(系统内置)',
                    protocol_name: '一号通 AI',
                    model: '由一号通平台决定',
                    supports_tools: 1,
                    is_default: hasRegisteredDefault ? 0 : 1
                },
                ...this.list
            ];
        }
    },
    watch: {
        // 模型名称默认自动跟随模型 ID，用户手动编辑过名称后停止同步
        'form.model'(val) {
            if (!this.nameTouched) {
                this.form.name = val;
            }
        }
    },
    mounted() {
        this.loadProtocols();
        this.loadList();
        this.loadYihaotongStatus();
    },
    methods: {
        loadProtocols() {
            aiModelProtocolsApi().then((res) => {
                this.protocols = res.data || [];
            });
        },
        // 加载一号通凭证配置状态（决定虚拟行操作列是「设为默认」还是「一键配置」）
        loadYihaotongStatus() {
            aiModelYihaotongStatusApi().then((res) => {
                this.yihaotongConfigured = (res.data || {}).configured === 1 ? 1 : 0;
            });
        },
        // 一键配置：跳转一号通设置页，官方页登录成功后凭证自动写入系统配置
        goYihaotong() {
            this.$router.push('/admin/setting/yihaotong');
        },
        loadList() {
            this.loading = true;
            aiModelListApi({ ...this.filter, page: this.page, limit: this.limit })
                .then((res) => {
                    const data = res.data || {};
                    this.list = data.list || [];
                    this.count = data.count || 0;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        search() {
            this.page = 1;
            this.loadList();
        },
        changePage(newPage) {
            this.page = newPage;
            this.loadList();
        },
        // 表单数据 → 弹窗布尔镜像
        applyFormBooleans() {
            this.form.supportsToolsBool = this.form.supports_tools === 1;
            this.form.supportsVisionBool = this.form.supports_vision === 1;
            this.form.isDefaultBool = this.form.is_default === 1;
        },
        // 弹窗布尔镜像 → 表单数据
        collectFormBooleans() {
            this.form.supports_tools = this.form.supportsToolsBool ? 1 : 0;
            this.form.supports_vision = this.form.supportsVisionBool ? 1 : 0;
            this.form.is_default = this.form.isDefaultBool ? 1 : 0;
        },
        /**
         * 打开编辑弹窗（无参为新增；有参拉详情回显）
         * @param {Object|undefined} row 模型行
         */
        openEditor(row) {
            if (!row) {
                this.form = emptyForm();
                this.nameTouched = false;
                this.advancedOpen = [];
                this.editorVisible = true;
                this.$nextTick(() => this.$refs.modelForm && this.$refs.modelForm.resetFields());
                return;
            }
            aiModelDetailApi(row.id).then((res) => {
                this.form = { ...emptyForm(), ...(res.data || {}) };
                this.nameTouched = !!this.form.name;
                this.applyFormBooleans();
                this.advancedOpen = [];
                this.editorVisible = true;
                this.$nextTick(() => this.$refs.modelForm && this.$refs.modelForm.resetFields());
            });
        },
        // 重置弹窗表单：新增时清空，编辑时还原为已保存数据
        resetForm() {
            if (this.form.id) {
                aiModelDetailApi(this.form.id).then((res) => {
                    this.form = { ...emptyForm(), ...(res.data || {}) };
                    this.nameTouched = !!this.form.name;
                    this.applyFormBooleans();
                    this.$nextTick(() => this.$refs.modelForm && this.$refs.modelForm.resetFields());
                });
            } else {
                this.form = emptyForm();
                this.nameTouched = false;
                this.$nextTick(() => this.$refs.modelForm && this.$refs.modelForm.resetFields());
            }
        },
        // 保存前先做连通性测试：测试通过才落库，失败时提示原因
        saveModel() {
            this.$refs.modelForm.validate((valid) => {
                if (!valid) return;
                this.collectFormBooleans();
                // 第一阶段：连通测试，按钮禁用并显示「连通测试中」
                this.savingPhase = 'test';
                const payload = { ...this.form };
                delete payload.id;
                delete payload.supportsToolsBool;
                delete payload.supportsVisionBool;
                delete payload.isDefaultBool;
                delete payload.virtual;
                aiModelTestApi({ ...this.form })
                    .then((res) => {
                        const test = res.data || {};
                        if (test.ok !== 1) {
                            this.$Message.error(test.message || '连通性测试失败，请检查模型配置');
                            return undefined;
                        }
                        // 第二阶段：落库，按钮切换为「保存中」
                        this.savingPhase = 'save';
                        const request = this.form.id ? aiModelUpdateApi(this.form.id, payload) : aiModelSaveApi(payload);
                        return request.then(() => {
                            this.$Message.success(this.form.id ? '修改成功' : '添加成功');
                            this.editorVisible = false;
                            this.loadList();
                            this.$emit('changed');
                        });
                    })
                    .catch(() => {})
                    .finally(() => {
                        // 无论成功失败恢复按钮状态，允许用户调整配置后重试
                        this.savingPhase = '';
                    });
            });
        },
        changeStatus(row) {
            aiModelStatusApi(row.id, row.status)
                .then(() => {
                    this.$Message.success('修改成功');
                    this.loadList();
                    this.$emit('changed');
                })
                .catch(() => {
                    row.status = row.status === 1 ? 0 : 1;
                });
        },
        setDefault(row) {
            if (row.is_default === 1 || (row.status !== 1 && !row.virtual)) return;
            aiModelSetDefaultApi(row.id)
                .then(() => {
                    this.$Message.success('已设为系统默认模型');
                    this.loadList();
                    this.$emit('changed');
                })
                .catch(() => {});
        },
        removeModel(row) {
            this.$Modal.confirm({
                title: '提示',
                content: `确定删除模型“${row.name}”吗？删除后对话中将无法再选择该模型。`,
                onOk: () => {
                    aiModelDeleteApi(row.id).then(() => {
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
.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.toolbar-title {
    color: #303133;
    font-size: 18px;
    font-weight: 600;
}
.toolbar-tip,
.muted,
.form-tip {
    margin-top: 6px;
    color: #909399;
    font-size: 13px;
}
.mt16 {
    margin-top: 16px;
}
.model-name {
    color: #303133;
    font-weight: 600;
}
.def-tag {
    margin-left: 6px;
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
.table-link.disabled {
    color: #c0c4cc;
    cursor: not-allowed;
}
.table-link.danger {
    color: #f56c6c;
}
.inline-tip {
    margin-left: 12px;
}
/* 弹窗底部：左侧连通性测试提示 + 右侧操作按钮 */
.dialog-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.footer-tip {
    color: #909399;
    font-size: 12px;
}
.footer-tip i {
    margin-right: 4px;
    color: #409eff;
}
/* 自定义请求地址：标签行（左标题 + 右完整URL开关） */
.base-url-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 4px;
}
.base-url-label {
    color: #606266;
    font-size: 14px;
    font-weight: 600;
}
.required-star {
    margin-left: 4px;
    color: #f56c6c;
    font-style: normal;
}
.base-url-switch {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #606266;
    font-size: 13px;
}
.base-url-switch i {
    color: #909399;
}
/* 表单字段下方提示文案 */
.field-tip {
    margin-top: 6px;
    color: #909399;
    font-size: 12px;
    line-height: 1.5;
}
/* 高级配置折叠面板 */
.advanced-collapse {
    margin-top: 4px;
    border-top: 1px solid #ebeef5;
}
.filter-bar {
    display: flex;
    align-items: center;
}
.pager {
    margin-top: 16px;
    text-align: right;
}
.pager-total {
    margin-right: 12px;
    color: #606266;
    font-size: 13px;
}
</style>

<style>
/* iView Table 的 render 函数内容不带 scoped 属性，表格内样式走组件根命名空间全局穿透 */
.model-manage .table-link {
    display: inline-block;
    margin-right: 14px;
    color: #0256ff;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
    transition: opacity 0.15s, color 0.15s;
}
.model-manage .table-link:last-child {
    margin-right: 0;
}
.model-manage .table-link:hover {
    opacity: 0.8;
    color: #0246cc;
}
.model-manage .table-link.disabled {
    color: #c0c4cc;
    cursor: not-allowed;
}
.model-manage .table-link.danger {
    color: #f56c6c;
}
.model-manage .table-link.danger:hover {
    color: #d9363e;
    opacity: 1;
}
.model-manage .model-name {
    display: flex;
    align-items: center;
    color: #303133;
    font-weight: 600;
}
.model-manage .model-name .def-tag {
    margin-left: 6px;
}
.model-manage td .muted {
    margin-top: 6px;
    color: #909399;
    font-size: 13px;
}
.model-manage .mono {
    font-family: Menlo, Consolas, monospace;
    font-size: 12px;
}
/* 一号通未配置提示（密钥列） */
.model-manage .yihaotong-missing {
    color: #f56c6c;
    font-size: 13px;
}
</style>
