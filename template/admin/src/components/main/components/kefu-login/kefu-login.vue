<template>
  <div v-if="hasAuth" class="kefu-login-con">
    <Dropdown trigger="click" placement="bottom-end" @on-visible-change="handleVisibleChange" @on-click="handleLoginKefu">
      <div class="kefu-login-btn">
        <Icon type="md-headset" :size="20"></Icon>
        <span class="kefu-login-text">客服访问</span>
        <Icon type="ios-arrow-down" :size="14"></Icon>
      </div>
      <DropdownMenu slot="list" class="kefu-dropdown-menu">
        <div class="kefu-login-title">选择系统客服进入工作台</div>
        <div v-if="loading" class="kefu-login-tip">
          <Spin size="small"></Spin>
        </div>
        <template v-else>
          <DropdownItem v-for="item in kefuList" :key="item.id" :name="String(item.id)" :disabled="submitting">
            <img v-if="!item.avatarBroken" class="kefu-login-avatar" :src="item.avatar" alt="" @error="item.avatarBroken = true">
            <span v-else class="kefu-login-avatar kefu-login-avatar-fallback">{{ (item.nickname || '客').charAt(0) }}</span>
            <span class="kefu-login-info">
              <span class="kefu-login-name">{{ item.nickname }}</span>
              <span class="kefu-login-account">{{ item.account }}</span>
            </span>
            <span class="kefu-login-online" :class="{ 'on': item.online == 1 }">{{ item.online == 1 ? '在线' : '离线' }}</span>
          </DropdownItem>
          <div v-if="!kefuList.length" class="kefu-login-tip">
            <Icon type="ios-people-outline" :size="30"></Icon>
            <p>暂无可登录的系统客服</p>
          </div>
        </template>
      </DropdownMenu>
    </Dropdown>
  </div>
</template>
<style lang="less">
/* 头部图标统一规格：36×36 热区、20px 图标、悬停主题蓝+浅蓝底（与全屏按钮一致） */
.kefu-login-con{
  margin-right: 17px;
}
.kefu-login-btn{
  height: 36px;
  margin-top: 14px;
  padding: 0 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #5c6b77;
  border-radius: 4px;
  cursor: pointer;
  transition: color .2s ease, background-color .2s ease;
  &:hover{
    color: #2D8cF0;
    background-color: #f0f7ff;
  }
}
.kefu-login-text{
  margin: 0 4px;
  font-size: 13px;
  white-space: nowrap;
}
/* 下拉面板：定宽卡片式列表。注意面板渲染在头部插槽内，必须重置继承自头部的 64px 行高 */
.kefu-dropdown-menu{
  width: 264px;
  padding: 4px 0 6px;
  line-height: normal;
  .kefu-login-title{
    padding: 9px 16px;
    font-size: 12px;
    line-height: 18px;
    color: #999;
    border-bottom: 1px solid #f2f2f2;
  }
  .kefu-login-tip{
    padding: 18px 16px;
    text-align: center;
    color: #999;
    font-size: 12px;
    p{
      margin-top: 6px;
    }
  }
  .ivu-dropdown-item{
    display: flex;
    align-items: center;
    margin: 4px 8px 0;
    padding: 8px 10px;
    border-radius: 6px;
    line-height: 1.2;
    &:hover{
      background-color: #f0f7ff;
      .kefu-login-name{
        color: #2D8cF0;
      }
    }
  }
  .kefu-login-avatar{
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    margin-right: 10px;
    background-color: #f2f2f2;
  }
  .kefu-login-avatar-fallback{
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #2D8cF0;
    color: #fff;
    font-size: 14px;
  }
  .kefu-login-info{
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
  }
  .kefu-login-name{
    font-size: 13px;
    color: #333;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: color .2s ease;
  }
  .kefu-login-account{
    margin-top: 3px;
    font-size: 12px;
    color: #999;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .kefu-login-online{
    flex-shrink: 0;
    margin-left: 10px;
    font-size: 12px;
    color: #999;
    &::before{
      content: '';
      display: inline-block;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background-color: #c5c8ce;
      margin-right: 4px;
      vertical-align: 1px;
    }
    &.on{
      color: #19be6b;
      &::before{
        background-color: #19be6b;
      }
    }
  }
}
</style>
<script>
import { kefuListApi, kefuLogin } from '@/api/setting'
import { setCookies } from '@/libs/util'
import { includeArray } from '@/libs/auth'

export default {
  name: 'KefuLogin',
  data () {
    return {
      loading: false,
      submitting: false,
      kefuList: []
    }
  },
  computed: {
    // 与「设置-客服管理」页面同权限，无权限的管理员不显示该入口
    hasAuth () {
      return includeArray(['setting-store-service'], this.$store.state.userInfo.uniqueAuth)
    }
  },
  methods: {
    // 下拉展开时拉取系统客服列表
    handleVisibleChange (visible) {
      if (visible) this.getKefuList()
    },
    getKefuList () {
      this.loading = true
      kefuListApi({ page: 1, limit: 100, group_id: 0 }).then(res => {
        // 仅展示已启用且配置了登录账号的客服
        this.kefuList = ((res.data && res.data.list) || []).filter(item => {
          return Number(item.status) === 1 && item.account
        }).map(item => Object.assign(item, { avatarBroken: false }))
      }).catch(() => {
        this.kefuList = []
      }).then(() => {
        this.loading = false
      })
    },
    // 越权登录选中的系统客服，新窗口打开客服工作台
    handleLoginKefu (id) {
      if (this.submitting) return
      this.submitting = true
      kefuLogin(id).then(res => {
        const data = res.data
        const expires = this.getExpiresTime(data.exp_time)
        // 与客服登录页(/kefu)保持一致的 cookie 写入
        setCookies('kefu_token', data.token, expires)
        setCookies('kefu_uuid', data.kefuInfo.uid, expires)
        setCookies('kefu_expires_time', data.exp_time, expires)
        setCookies('kefuInfo', data.kefuInfo, expires)
        this.$store.commit('kefu/setInfo', data.kefuInfo)
        this.$Message.success(`已进入 ${data.kefuInfo.nickname} 的客服工作台`)
        window.open(window.location.protocol + '//' + window.location.host + '/kefu/pc_list', '_blank')
      }).catch(error => {
        this.$Message.error((error && error.msg) || '进入客服工作台失败')
      }).then(() => {
        this.submitting = false
      })
    },
    // 后端返回秒级过期时间戳，换算为 cookie 天数
    getExpiresTime (expiresTime) {
      const nowTimeNum = Math.round(new Date() / 1000)
      const expiresTimeNum = expiresTime - nowTimeNum
      return parseFloat(parseFloat(parseFloat(expiresTimeNum / 60) / 60) / 24)
    }
  }
}
</script>
