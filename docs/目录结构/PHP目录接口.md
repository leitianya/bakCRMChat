## 后端文件目录结构

> 目录结构只列出框架（ThinkPHP6）之外的业务内容,如需了解其他目录结构请查看:ThinkPHP6官网的文档说明

#### APP目录结构
``` 
├─controller
│  │  InstallController.php
│  │  
│  ├─admin    总后台模块
│  │  │  Application.php   应用控制器
│  │  │  AuthController.php 基类控制器
│  │  │  Common.php  公用控制器
│  │  │  Index.php  主页
│  │  │  Login.php  登录
│  │  │  Test.php  测试
│  │  │  
│  │  ├─chat  客服
│  │  │      Service.php 客服
│  │  │      ServiceFeedback.php 反馈
│  │  │      ServiceSpeechcraft.php 话术
│  │  │      ServiceSpeechcraftCate.php 话术分类
│  │  │      
│  │  ├─file 文件
│  │  │      Attachment.php  附件
│  │  │      AttachmentCategory.php 附件分类
│  │  │      
│  │  ├─system 配置
│  │  │      Admin.php 管理员
│  │  │      Config.php 配置
│  │  │      ConfigTab.php 配置分类
│  │  │      Group.php 组合数据
│  │  │      GroupData.php 组合数据数据
│  │  │      Log.php 日志
│  │  │      Menus.php 菜单
│  │  │      Role.php 权限
│  │  │      
│  │  └─user 用户
│  │          Group.php 分组
│  │          Label.php 标签
│  │          LabelCate.php 标签分类
│  │          User.php 用户
│  │          
│  ├─kefu 客服模块
│  │      AuthController.php 基础控制器
│  │      Login.php 登录
│  │      Service.php 客服
│  │      Statistics.php 统计
│  │      User.php 用户
│  │      
│  └─mobile 移动端模块
│          AuthController.php  基类控制器
│          Feedback.php 反馈
│          Service.php 客服
│          
├─dao Dao层
│  │  ApplicationDao.php 应用
│  │  
│  ├─chat 客服
│  │  │  ChatServiceAuxiliaryDao.php 客服关联
│  │  │  ChatServiceDao.php 客服
│  │  │  ChatServiceDialogueRecordDao.php 聊天记录日志
│  │  │  ChatServiceFeedbackDao.php 反馈
│  │  │  ChatServiceRecordDao.php 聊天记录
│  │  │  ChatServiceSpeechcraftDao.php 话术
│  │  │  ChatUserDao.php 用户
│  │  │  
│  │  └─user 用户
│  │          ChatUserGroupDao.php 用户分组
│  │          ChatUserLabelAssistDao.php 标签辅助
│  │          ChatUserLabelDao.php 标签
│  │          
│  ├─other 其他
│  │      AuxiliaryDao.php 辅助表
│  │      CacheDao.php 数据库缓存表
│  │      CategoryDao.php 分类表
│  │      
│  └─system 系统配置
│      │  SystemMenusDao.php 菜单
│      │  
│      ├─admin 权限
│      │      AdminAuthDao.php 登录dao
│      │      SystemAdminDao.php 管理员
│      │      SystemRoleDao.php 权限
│      │      
│      ├─attachment 附件
│      │      SystemAttachmentCategoryDao.php 附件分类
│      │      SystemAttachmentDao.php 附件
│      │      
│      ├─config 配置
│      │      SystemConfigDao.php 配置
│      │      SystemConfigTabDao.php 配置分类
│      │      SystemGroupDao.php 组合数据
│      │      SystemGroupDataDao.php 组合数据数据记录
│      │      
│      └─log 日志
│              SystemFileDao.php 文件检测
│              SystemLogDao.php 日志记录
│              
├─http 
│  └─middleware 中间件
│      │  AllowOriginMiddleware.php 跨域中间件
│      │  InstallMiddleware.php 安装中间件
│      │  
│      ├─admin 总后台模块中间件
│      │      AdminAuthTokenMiddleware.php  登录中间件
│      │      AdminCkeckRoleMiddleware.php  权限验证中间件
│      │      AdminLogMiddleware.php  日志中间件
│      │      
│      ├─kefu 客服模块中间件
│      │      KefuAuthTokenMiddleware.php 客服登录中间件
│      │      
│      └─mobile 移动端中间件
│              MobileAuthTokenMiddleware.php 应用检测中间件
│              
├─jobs
├─lang 多语言
│      zh-cn.php
│      
├─listener 普通事件类存放
├─models 模型
│  │  Application.php 应用
│  │  
│  ├─chat 客服
│  │  │  ChatService.php
│  │  │  ChatServiceDialogueRecord.php
│  │  │  ChatServiceFeedback.php
│  │  │  ChatServiceRecord.php
│  │  │  ChatServiceSpeechcraft.php
│  │  │  ChatUser.php
│  │  │  
│  │  └─user 客服用户
│  │          ChatUserGroup.php
│  │          ChatUserLabel.php
│  │          ChatUserLabelAssist.php
│  │          
│  ├─other 其他
│  │      Auxiliary.php
│  │      Cache.php
│  │      Category.php
│  │      
│  └─system 系统配置
│      │  SystemMenus.php
│      │  
│      ├─admin 权限
│      │      SystemAdmin.php
│      │      SystemRole.php
│      │      
│      ├─attachment 附件
│      │      SystemAttachment.php
│      │      SystemAttachmentCategory.php
│      │      
│      ├─config 配置
│      │      SystemConfig.php
│      │      SystemConfigTab.php
│      │      SystemGroup.php
│      │      SystemGroupData.php
│      │      
│      └─log 日志
│              SystemFile.php
│              SystemLog.php
│              
├─services 逻辑处理层
│  │  ApplicationServices.php 应用
│  │  
│  ├─chat 客服
│  │  │  ChatServiceAuxiliaryServices.php
│  │  │  ChatServiceDialogueRecordServices.php
│  │  │  ChatServiceFeedbackServices.php
│  │  │  ChatServiceRecordServices.php
│  │  │  ChatServiceServices.php
│  │  │  ChatServiceSpeechcraftCateServices.php
│  │  │  ChatServiceSpeechcraftServices.php
│  │  │  ChatUserServices.php
│  │  │  
│  │  └─user 客服用户
│  │          ChatUserGroupServices.php
│  │          ChatUserLabelAssistServices.php
│  │          ChatUserLabelCateServices.php
│  │          ChatUserLabelServices.php
│  │          
│  ├─kefu 客服模块逻辑层
│  │      AuthServices.php
│  │      KefuServices.php
│  │      LoginServices.php
│  │      
│  ├─other 其他
│  │      CacheServices.php
│  │      CategoryServices.php
│  │      
│  └─system 系统配置
│      │  SystemMenusServices.php
│      │  
│      ├─admin 权限
│      │      AdminAuthServices.php
│      │      SystemAdminServices.php
│      │      SystemRoleServices.php
│      │      
│      ├─attachment 附件
│      │      SystemAttachmentCategoryServices.php
│      │      SystemAttachmentServices.php
│      │      
│      ├─config 配置
│      │      SystemConfigServices.php
│      │      SystemConfigTabServices.php
│      │      SystemGroupDataServices.php
│      │      SystemGroupServices.php
│      │      
│      └─log 日志
│              ClearServices.php
│              SystemFileServices.php
│              SystemLogServices.php
│              
├─validate 数据验证
│  │  Test.php
│  │  
│  ├─chat 客服
│  │      ChatServiceFeedbackValidate.php
│  │      ChatServiceValidate.php
│  │      
│  ├─kefu 客服总后台
│  │      LoginValidate.php
│  │      SpeechcraftValidate.php
│  │      
│  └─system 
│          SystemAdminValidata.php
│          SystemConfigValidata.php
│          
├─webscoket 长连接
│    │  BaseHandler.php 事件基类
│    │  Manager.php 
│    │  Ping.php 心跳
│    │  Response.php socket response
│    │  Room.php  房间
│    │  SwooleWorkerStart.php swoole启动后置事件
│    │  
│    └─handler 事件回调处理
│         AdminHandler.php
│         KefuHandler.php
│         UserHandler.php
│  AppService.php 
│  build.php
│  common.php
│  event.php
│  ExceptionHandle.php
│  middleware.php
│  provider.php
│  Request.php
│  Route.php
└─ service.php

```

#### crmeb 目录结构

```
├─basic 基类存放
│      BaseDao.php
│      BaseExpress.php
│      BaseJobs.php
│      BaseManager.php
│      BaseModel.php
│      BaseServices.php
│      BaseSmss.php
│      BaseStorage.php
│      BaseUpload.php
│      
├─command 命令行存放
│  │  Dao.php
│  │  Install.php
│  │  Key.php
│  │  Service.php
│  │  
│  └─stubs 模板
│          dao.stub
│          service.stub
│          
├─exceptions 错误异常类
│      AdminException.php
│      ApiException.php
│      AuthException.php
│      DecryptException.php
│      EncryptException.php
│      MissingAppKeyException.php
│      UploadException.php
│      
├─interfaces 接口类存放
│      HandlerInterface.php
│      JobInterface.php
│      ListenerInterface.php
│      MiddlewareInterface.php
│      ProviderInterface.php
│      
├─listeners 系统事件订阅类存放
│  │  InitSwooleLockListen.php
│  │  Listener.php
│  │  SwooleShutdownListen.php
│  │  SwooleStartListen.php
│  │  SwooleTaskListen.php
│  │  TestListen.php
│  │  
│  └─user
│          UserLogin.php
│          
├─services 系统内置服务
│  │  AccessTokenServeService.php 一号通服务平台token
│  │  CacheService.php 缓存
│  │  FormBuilder.php 表单
│  │  GroupDataService.php  组合数据
│  │  HttpService.php  curl
│  │  SwooleTaskService.php swoole任务
│  │  SystemConfigService.php 数据配置
│  │  UploadService.php 上传
│  │  
│  ├─express 物流
│  │  │  Express.php
│  │  │  
│  │  └─storage
│  │          Express.php
│  │          
│  ├─serve 一号通服务
│  │  │  Serve.php
│  │  │  
│  │  └─storage
│  │          Crmeb.php
│  │          
│  ├─sms 短信服务
│  │  │  Sms.php
│  │  │  
│  │  └─storage
│  │          Yunxin.php
│  │          
│  └─upload 上传服务
│      │  Upload.php
│      │  
│      └─storage
│              Cos.php
│              Local.php
│              Oss.php
│              Qiniu.php
│              
├─subscribes
│      TaskSubscribe.php
│      
├─traits 
│      ErrorTrait.php 错误
│      JwtAuthModelTrait.php 中间件
│      ModelTrait.php 模型
│      QueueTrait.php 消息队列
│      Schema.php
│      ServicesTrait.php service搜索辅助
│      TimeModelTrait.php 时间查询模型辅助
│      
└─utils 
        ApiErrorCode.php 接口返回状态
        Arr.php   数组帮助
        Blueprint.php 
        Canvas.php 画图
        Captcha.php 验证码
        Character.php 中文排序
        Encrypter.php 加密解密
        Hook.php 
        Json.php json返回
        JwtAuth.php jwt辅助
        QRcode.php 二维码生成重写
        Queue.php 消息队列封装辅助
        Str.php 字符串辅助
```
