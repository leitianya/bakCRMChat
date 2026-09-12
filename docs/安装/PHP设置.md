## 步骤总结

一、安装PHP插件：`fileinfo`、`redis`、`swoole4`。
二、删除PHP对应版本中的 `proc_open`禁用函数。

## 步骤详解：

1. 进入宝塔面板点击 **软件商城** ,点击 **PHP设置** .这里以`PHP7.3`为例;
![输入图片说明](https://images.gitee.com/uploads/images/2021/0721/162644_0c0b41d6_1491977.png "屏幕截图.png")
2. 进入安装扩展,安装:`fileinfo`、`redis`、`swoole4` 扩展插件
![输入图片说明](https://images.gitee.com/uploads/images/2021/0721/162810_3180eade_1491977.png "屏幕截图.png")
![输入图片说明](https://images.gitee.com/uploads/images/2021/0721/162820_cf3ec9f0_1491977.png "屏幕截图.png")
3. 进入 **禁用函数** ,找到 `proc_open` 删除
![输入图片说明](https://images.gitee.com/uploads/images/2021/0721/162906_764be12a_1491977.png "屏幕截图.png")
4. 进入 **服务** ,选择重载配置
![输入图片说明](https://images.gitee.com/uploads/images/2021/0721/162945_8e3afe55_1491977.png "屏幕截图.png")
5. PHP配置完成.进入站点配置