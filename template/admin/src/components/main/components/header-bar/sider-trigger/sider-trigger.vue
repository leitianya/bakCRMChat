<template>
  <Tooltip :content="collapsed ? '展开侧边菜单' : '收起侧边菜单'" placement="bottom" class="sider-trigger-tip">
    <a @click="handleChange" type="text" :class="['sider-trigger-a', collapsed ? 'collapsed' : '']">
      <!-- 面板式方向图标：展开时箭头朝左（收起），收起后箭头翻转朝右（展开） -->
      <svg v-if="icon === 'panel'" class="panel-trigger-icon" viewBox="0 0 24 24" fill="none" :style="{ width: size + 'px', height: size + 'px' }">
        <rect x="3.25" y="4.75" width="17.5" height="14.5" rx="2.75" stroke="currentColor" stroke-width="1.7" />
        <line x1="9.4" y1="4.75" x2="9.4" y2="19.25" stroke="currentColor" stroke-width="1.7" />
        <polyline class="panel-trigger-arrow" points="16.3,9.6 14.1,12 16.3,14.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <Icon v-else :type="icon" :size="size" />
    </a>
  </Tooltip>
</template>
<script>
    export default {
        name: 'siderTrigger',
        props: {
            collapsed: Boolean,
            icon: {
                type: String,
                default: 'md-menu'
            },
            size: {
                type: Number,
                default: 20
            }
        },
        methods: {
            handleChange () {
                this.$emit('on-change', !this.collapsed)
            }
        }
    }
</script>
<style lang="less">
@import './sider-trigger.less';
</style>
