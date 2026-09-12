<template>
    <div class="article-manager">

        <Card :bordered="false" dis-hover class="ivu-mt">
            <Form class="form" ref="formValidate" :model="formValidate" :rules="ruleValidate" :label-width="labelWidth"
                  :label-position="labelPosition" @submit.native.prevent>
                <div class="goodsTitle acea-row">
                    <div class="title">隐私协议：</div>
                </div>
                <FormItem label="展示内容：" prop="content">
                    <wang-editor v-model="formValidate.content" :height="500"></wang-editor>
                </FormItem>


                <Button type="primary" class="submission" @click="onsubmit('formValidate')">提交</Button>
            </Form>
        </Card>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import WangEditor from '@/components/wangEditor'
    import {getUserAgreementApi, saveUserAgreementApi} from '@/api/system'

    export default {
        name: 'setting_privacy',
        components: {WangEditor},
        data() {
            return {
                dialog: {},
                isChoice: '单选',
                grid: {
                    xl: 8,
                    lg: 8,
                    md: 12,
                    sm: 24,
                    xs: 24
                },
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
                loading: false,
                formValidate: {
                    content: '',
                },
                ruleValidate: {
                    // content: [
                    //     {required: true, message: '请输入内容', trigger: 'change'}
                    // ]
                },
                value: '',
                modalPic: false,
                template: false,
                treeData: []
            }
        },
        computed: {
            ...mapState('media', [
                'isMobile'
            ]),
            labelWidth() {
                return this.isMobile ? undefined : 120
            },
            labelPosition() {
                return this.isMobile ? 'top' : 'right'
            }
        },
        watch: {
            $route(to, from) {
                this.getPrivacy()
            }
        },
        methods: {
            // ...mapActions('admin/page', [
            //   'close'
            // ]),
            getContent(val) {
                this.formValidate.content = val
                console.log(this.formValidate.content)
            },
            // 提交数据
            onsubmit(name) {
                this.$refs[name].validate((valid) => {
                    if (valid) {
                        saveUserAgreementApi(this.formValidate).then(async res => {
                            this.$Message.success(res.msg)
                        }).catch(res => {
                            this.$Message.error(res.msg)
                        })
                    } else {
                        return false
                    }
                })
            },
            //详情
            getPrivacy() {
                getUserAgreementApi().then(async res => {
                    let data = res.data
                    this.formValidate = {
                        content: data.content || '',
                    }
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            }
        },
        mounted() {
            this.getPrivacy()
        }
    }
</script>
<style scoped>
    .picBox {
        display: inline-block;
        cursor: pointer;
    }

    .form .goodsTitle {
        border-bottom: 1px solid rgba(0, 0, 0, 0.09);
        margin-bottom: 25px;
    }

    .form .goodsTitle ~ .goodsTitle {
        margin-top: 20px;
    }

    .form .goodsTitle .title {
        border-bottom: 2px solid #1890FF;
        padding: 0 8px 12px 5px;
        color: #000;
        font-size: 14px;
    }

    .form .goodsTitle .icons {
        font-size: 15px;
        margin-right: 8px;
        color: #999;
    }

    .form .add {
        font-size: 12px;
        color: #1890FF;
        padding: 0 12px;
        cursor: pointer;
    }

    .form .radio {
        margin-right: 20px;
    }

    .form .submission {
        width: 10%;
        margin-left: 27px;
    }

    .form .upLoad {
        width: 58px;
        height: 58px;
        line-height: 58px;
        border: 1px dotted rgba(0, 0, 0, 0.1);
        border-radius: 4px;
        background: rgba(0, 0, 0, 0.02);
    }

    .form .iconfont {
        color: #898989;
    }

    .form .pictrue {
        width: 60px;
        height: 60px;
        border: 1px dotted rgba(0, 0, 0, 0.1);
        margin-right: 10px;
    }

    .form .pictrue img {
        width: 100%;
        height: 100%;
    }

    .Modals .address {
        width: 90%;
    }

    .Modals .address .iconfont {
        font-size: 20px;
    }
</style>
