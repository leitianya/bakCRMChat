<template>
    <div class="yihaotong-page">
        <Card :bordered="false" dis-hover class="ivu-mt">
            <div class="yihaotong-tip">
                <Icon type="ios-information-circle" /> 在下方平台页面完成登录后，AppId 与 AppSecret 将自动写入本系统配置（一号通 AI / 短信等服务共用）。
            </div>
            <iframe :src="platformUrl" class="yihaotong-iframe" frameborder="0"></iframe>
        </Card>
    </div>
</template>

<script>
// 一号通设置（参考 crmeb 标准版 pages/yihaotong/index.vue 移植，Vue 2 + iView 适配版）
//
// 内嵌一号通官方平台登录页 iframe；登录成功后平台 postMessage 回传
// { accessKey, secretKey }（即一号通签发的 AppId/AppSecret），写入系统配置
// yihaotong_appid / yihaotong_appsecret，供一号通 AI、短信等服务共用。
import { saveYihaotongConfig } from '@/api/system';

export default {
    name: 'setting_yihaotong',
    data() {
        return {
            platformUrl: 'https://api.crmeb.com?token=AF37D4579721672220B08CA872586943',
            // 凭证保存中（防 iframe 重复 postMessage 导致重复提交）
            saving: false
        };
    },
    methods: {
        // 接收 iframe 登录成功后传出的平台凭证并保存
        handleConfig(event) {
            const payload = event && event.data;
            if (!payload || !payload.accessKey || !payload.secretKey) return;
            if (this.saving) return;
            this.saving = true;
            saveYihaotongConfig({
                yihaotong_appid: payload.accessKey,
                yihaotong_appsecret: payload.secretKey
            })
                .then(() => {
                    this.$Message.success('一号通登录成功，AppId 与 AppSecret 已自动写入系统配置');
                })
                .catch((res) => {
                    this.saving = false;
                    this.$Message.error((res && res.msg) || '一号通配置保存失败');
                });
        }
    },
    mounted() {
        window.addEventListener('message', this.handleConfig);
    },
    beforeDestroy() {
        window.removeEventListener('message', this.handleConfig);
    }
};
</script>

<style scoped>
.yihaotong-tip {
    margin-bottom: 12px;
    font-size: 13px;
    color: #808695;
}
.yihaotong-iframe {
    width: 100%;
    height: calc(100vh - 240px);
    border: 1px solid #e8eaec;
    border-radius: 4px;
    background: #fff;
}
</style>
