<template>
  <div class="header-bar">
    <sider-trigger :collapsed="collapsed" icon="panel" @on-change="handleCollpasedChange"></sider-trigger>
    <custom-bread-crumb show-icon :list="breadCrumbList" :listLast="crumbPast" :collapsed="collapsed"></custom-bread-crumb>
    <div class="custom-content-con">
      <slot></slot>
    </div>
  </div>
</template>
<script>
    import siderTrigger from './sider-trigger'
    import customBreadCrumb from './custom-bread-crumb'
    import { R } from '@/libs/util'

    import './header-bar.less'
    export default {
        name: 'HeaderBar',
        components: {
            siderTrigger,
            customBreadCrumb
        },
        props: {
            collapsed: Boolean
        },
        computed: {
            breadCrumbList () {
                let openMenus = this.$store.state.menus.openMenus
                let menuList = this.$store.state.menus.menusName
                let allMenuList = R(menuList, [])
                let selectMenu = []
                if (allMenuList.length > 0) {
                    openMenus.forEach((i) => {
                        allMenuList.forEach((a) => {
                            if (i === a.path) {
                                selectMenu.push(a)
                            }
                        })
                    })
                }
                return selectMenu
                // return this.$store.state.app.breadCrumbList
            },
            crumbPast () {
                let that = this
                let menuList = that.$store.state.menus.menusName
                let allMenuList = R(menuList, [])
                let selectMenu = []
                if (allMenuList.length > 0) {
                    allMenuList.forEach((a) => {
                        if (that.$route.path === a.path) {
                            selectMenu.push(a)
                        }
                    })
                }
                console.log(selectMenu)
                return selectMenu
            }
        },
        mounted () {

        },
        methods: {
            handleCollpasedChange (state) {
                this.$emit('on-coll-change', state)
            }
        }
    }
</script>
