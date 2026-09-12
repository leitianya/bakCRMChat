## APP打包和调试

此项目由uniapp框架编写，需要使用HBuilder X编译器进行运行和发布，支持浏览器调试，支持发行为APP和H5，其他发行方式不支持。需要HBuilder X安装less插件、sass插件、stylus编译插件，运行前请先安装。

本项目 APP 源码（uni-app）位于仓库 `template/uniapp/` 目录下（官方发行包中位于 `view/app` 目录）。调试前的域名配置参见[UNIAPP调试](./UNIAPP调试.md)。

### 配置
#### 一、配置APP应用ID
打开编译器点击`manifest.json`,需要重新获取应用标识。这一步是必须的，其他选项根据自身需求更换名称。

![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/153919_8046dc52_1491977.png "屏幕截图.png")
#### 二、配置APP图标
打开编译器点击`manifest.json`,点击浏览选择一张`1024*1024`png格式的图片，然后点击自动生成所有图标并替换会自动生成图标
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/154223_89140db9_1491977.png "屏幕截图.png")
#### 三、APP模块配置
打开编译器点击`manifest.json`,点击左侧APP模块配置，找到Push(消息推送)并勾选，其他模块暂时不用配置
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/154530_f0a59209_1491977.png "屏幕截图.png")
#### 四、APP权限配置
打开编译器点击`manifest.json`，需要增加如下权限。点击源码视图，搜索「android打包配置」，将下方权限数组粘贴到`permissions`数组里面

```
[
                    "<uses-feature android:name=\"android.hardware.camera\"/>",
                    "<uses-feature android:name=\"android.hardware.camera.autofocus\"/>",
                    "<uses-permission android:name=\"android.permission.ACCESS_COARSE_LOCATION\"/>",
                    "<uses-permission android:name=\"android.permission.ACCESS_NETWORK_STATE\"/>",
                    "<uses-permission android:name=\"android.permission.ACCESS_WIFI_STATE\"/>",
                    "<uses-permission android:name=\"android.permission.CAMERA\"/>",
                    "<uses-permission android:name=\"android.permission.CHANGE_NETWORK_STATE\"/>",
                    "<uses-permission android:name=\"android.permission.CHANGE_WIFI_STATE\"/>",
                    "<uses-permission android:name=\"android.permission.FLASHLIGHT\"/>",
                    "<uses-permission android:name=\"android.permission.INTERNET\"/>",
                    "<uses-permission android:name=\"android.permission.MODIFY_AUDIO_SETTINGS\"/>",
                    "<uses-permission android:name=\"android.permission.MOUNT_UNMOUNT_FILESYSTEMS\"/>",
                    "<uses-permission android:name=\"android.permission.READ_LOGS\"/>",
                    "<uses-permission android:name=\"android.permission.READ_PHONE_STATE\"/>",
                    "<uses-permission android:name=\"android.permission.READ_SMS\"/>",
                    "<uses-permission android:name=\"android.permission.SEND_SMS\"/>",
                    "<uses-permission android:name=\"android.permission.VIBRATE\"/>",
                    "<uses-permission android:name=\"android.permission.WAKE_LOCK\"/>",
                    "<uses-permission android:name=\"android.permission.WRITE_EXTERNAL_STORAGE\"/>",
                    "<uses-permission android:name=\"android.permission.WRITE_SETTINGS\"/>",
                    "<uses-permission android:name=\"android.permission.WRITE_SMS\"/>"
                ]
```
如下图
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/155057_6bd9ae26_1491977.png "屏幕截图.png")

IOS隐私信息访问描述配置，点击左侧APP权限配置找到IOS隐私信息访问描述，需要配置说明：相册-读、相册-写、摄像头、访问本地网络
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/155351_f9315816_1491977.png "屏幕截图.png")

### 调试
调试可在浏览器上调试或者真机调试，真机调试需要使用数据线连接HBuilder X编译器，安卓需要打开 **USB调试模式** 
#### 一、浏览器调试
1. 点击菜单栏的运行->运行到浏览器->chrome
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/153401_205e2c69_1491977.png "屏幕截图.png")
2. 运行到浏览器上后，会直接进入登录页面，点击修改域名
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/153540_349b0da5_1491977.png "屏幕截图.png")
3. 输入自己安装配置的域名和请求协议点击确定，再次进入登录页面，输入账号密码就可以登录使用
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/153634_a9fb771c_1491977.png "屏幕截图.png")

### 打包

#### 一、申请打包证书
- 安卓打包证书生成文档：https://ask.dcloud.net.cn/article/35777
- IOS打包证书生成文档：https://ask.dcloud.net.cn/article/152
#### 二、安卓打包
打开编译器，点击菜单栏 发行 -> 原生APP-云打包，输入申请的证书，勾选打正式包、安心打包。点击打包等待服务器打包完成。
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/161040_27f59793_1491977.png "屏幕截图.png")
#### 三、IOS打包
打开编译器，点击菜单栏 发行 -> 原生APP-云打包，输入申请的证书，勾选打正式包、安心打包。点击打包等待服务器打包完成。打包方式和安卓差不多一样
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/161830_33992b00_1491977.png "屏幕截图.png")
#### 四、上传IOS应用，实际上ios打包和安卓没有太大区别，只要证书申请下来，打包也不是什么问题，至于打包后怎么上传IOS应用，请看以下说明
1. windows用户可以下载appuploader软件[appuploader官网](http://www.applicationloader.net/)
2. 使用appuploader软件，点击certifcation弹出登录页面进行登录
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/162244_13f44c0d_1491977.png "屏幕截图.png")
3. 点击IPA，输入apple账号email账号，输入app专用密码，如果没有请查看：[apple专用密码说明](https://support.apple.com/zh-cn/HT204397)。点击save保存专用密码。后进入上传APP界面，选择下载好的IPA文件上传
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/162559_cc2df6bc_1491977.png "屏幕截图.png")
#### 五、IOS应用发布
1. 登录APPLE应用管理后台[https://appstoreconnect.apple.com/login](https://appstoreconnect.apple.com/login)
2. 没有APP则新建一个，然后点击刚创建的app进入管理
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/163704_2c642d98_1491977.png "屏幕截图.png")
3. 需要填写综合项内的APP信息、价格与销售范围、APP隐私。APP隐私需要填写一个隐私政策地址，隐私内容如：https://chat.crmeb.net/chat.html。实际情况下可按照此隐私协议修改为自己的协议
![输入图片说明](https://images.gitee.com/uploads/images/2021/1112/163943_9ce8f286_1491977.png "屏幕截图.png")
4. 准备提交版本，需要填写APP预览和截图（iphone6.5和5.5显示屏至少两个）、关键字（根据自身填写）、描述（根据自身填写）、构建版本（这里的构建版本就是在刚才上传的ipa文件会展示到当前界面的TestFlight菜单里面，管理员可下载TestFlight应用进行测试）、App审核信息（利于过审）、版本发布（选择自动发布此版本）。填写好后点击存储，然后再点击提交以供审核。ios的一个版本就等待审核了。
