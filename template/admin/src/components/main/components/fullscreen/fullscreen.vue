<template>
  <div v-if="showFullScreenBtn" class="full-screen-btn-con">
    <Tooltip :content="value ? '退出全屏' : '全屏'" placement="bottom">
      <Icon @click.native="handleChange" :type="value ? 'ios-contract' : 'ios-qr-scanner'" :size="20"></Icon>
    </Tooltip>
  </div>
</template>

<script>
    export default {
        name: 'Fullscreen',
        computed: {
            showFullScreenBtn () {
                return window.navigator.userAgent.indexOf('MSIE') < 0
            }
        },
        props: {
            value: {
                type: Boolean,
                default: false
            }
        },
        methods: {
            handleFullscreen () {
                let main = document.body
                if (this.value) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen()
                    } else if (document.mozCancelFullScreen) {
                        document.mozCancelFullScreen()
                    } else if (document.webkitCancelFullScreen) {
                        document.webkitCancelFullScreen()
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen()
                    }
                } else {
                    if (main.requestFullscreen) {
                        main.requestFullscreen()
                    } else if (main.mozRequestFullScreen) {
                        main.mozRequestFullScreen()
                    } else if (main.webkitRequestFullScreen) {
                        main.webkitRequestFullScreen()
                    } else if (main.msRequestFullscreen) {
                        main.msRequestFullscreen()
                    }
                }
            },
            handleChange () {
                this.handleFullscreen()
            }
        },
        mounted () {
            let isFullscreen = document.fullscreenElement || document.mozFullScreenElement || document.webkitFullscreenElement || document.fullScreen || document.mozFullScreen || document.webkitIsFullScreen
            isFullscreen = !!isFullscreen
            document.addEventListener('fullscreenchange', () => {
                this.$emit('input', !this.value)
                this.$emit('on-change', !this.value)
            })
            document.addEventListener('mozfullscreenchange', () => {
                this.$emit('input', !this.value)
                this.$emit('on-change', !this.value)
            })
            document.addEventListener('webkitfullscreenchange', () => {
                this.$emit('input', !this.value)
                this.$emit('on-change', !this.value)
            })
            document.addEventListener('msfullscreenchange', () => {
                this.$emit('input', !this.value)
                this.$emit('on-change', !this.value)
            })
            this.$emit('input', isFullscreen)
        }
    }
</script>

<style lang="less">
/* 头部图标统一规格：36×36 热区、20px 图标、悬停主题蓝+浅蓝底 */
/* 水平 5px 边距：相邻图标两个 5px 叠加为统一 10px 间距 */
.full-screen-btn-con .ivu-tooltip-rel{
  width: 36px;
  height: 36px;
  margin: 14px 5px 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #5c6b77;
  border-radius: 4px;
  cursor: pointer;
  transition: color .2s ease, background-color .2s ease;
}
.full-screen-btn-con .ivu-tooltip-rel:hover{
  color: #2D8cF0;
  background-color: #f0f7ff;
}
</style>
