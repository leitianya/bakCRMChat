# 管理后台前端目录结构

> 管理后台前端位于仓库 `template/admin/` 目录，基于 **Vue 2 + iView（view-design）**，由 Vue CLI 构建，结构类似 iview-admin。客服端 APP（uni-app）见 [UNIAPP调试](../使用文档/UNIAPP调试.md)。

## 根目录

```
template/admin/
├── public/                  # 静态资源与 html 模板（index.html、UEditor 富文本、favicon）
├── src/                     # 源码（见下）
├── package.json             # 依赖与脚本（serve / build / lint）
├── vue.config.js            # Vue CLI 配置（devServer、代理、打包路径等）
└── cypress.json             # E2E 测试配置
```

## src 目录

```
src/
├── api/                     # 接口调用（与后端 route/ 下的接口一一对应）
│   ├── account.js           # 个人账号
│   ├── kefu.js              # 客服管理
│   ├── system.js            # 系统设置
│   ├── systemAdmin.js       # 管理员
│   ├── systemMenus.js       # 菜单
│   ├── user.js              # 用户
│   ├── uploadPictures.js    # 图片上传
│   └── ...
├── assets/                  # 图片、字体等静态资源
├── components/              # 公共组件
│   ├── uploadPictures/      # 图片上传选择
│   ├── customerInfo/        # 客户信息
│   ├── echarts / echartsNew # 图表
│   ├── from / iconFrom / searchFrom / publicSearchFrom  # 表单与搜索表单
│   ├── quill / mde / ueditorFrom  # 富文本编辑器
│   ├── verifition/          # 验证码校验
│   ├── icons / common-icon  # 图标
│   ├── main / copyright / modelSure / parent-view  # 布局与通用件
│   └── userLabel.vue        # 用户标签
├── config/                  # 前端全局配置
├── directive/               # 自定义指令
├── filters/                 # 过滤器
├── libs/                    # 工具库
│   ├── axios.js / request.js / api.request.js  # 请求封装
│   ├── socket.js            # WebSocket 封装（对接 swoole 20108）
│   ├── auth.js              # 登录态
│   ├── customerServer.js    # 客服组件辅助
│   └── tools.js / util.js / excel.js / dialog.js ...
├── locale/                  # 多语言
├── mock/                    # 本地 mock 数据
├── pages/                   # 页面（按后台菜单模块分包）
│   ├── account/             # 个人中心
│   ├── index/               # 首页 / 仪表盘
│   ├── kefu/                # 客服管理（客服列表、聊天记录、统计、二维码、外部链接等）
│   ├── setting/             # 设置（系统设置、客服参数、管理员、菜单、角色、版本等）
│   ├── system/              # 系统维护（日志、文件检测、缓存等）
│   └── user/                # 用户管理（用户列表、分组、标签）
├── plugin/                  # 插件
├── router/                  # 路由
│   ├── index.js / routers.js
│   └── modules/             # 按模块拆分的路由（kefu.js、setting.js、system.js、user.js、cms.js 等）
├── store/                   # Vuex 状态管理
├── styles/                  # 全局样式
├── utils/                   # 通用工具（emoji、城市数据、事件总线等）
├── App.vue                  # 根组件
├── main.js                  # 入口
└── setting.js               # 站点标题、版权等全局显示配置
```

## 常用命令

在 `template/admin/` 目录下执行：

```bash
npm install       # 安装依赖
npm run serve     # 本地开发调试
npm run build     # 打包，产物在 template/admin/dist/
npm run lint      # 代码检查
```

> 打包后的静态文件如何部署、如何对接后端，见[后台前端打包](../开发文档/后台前端打包.md)。
