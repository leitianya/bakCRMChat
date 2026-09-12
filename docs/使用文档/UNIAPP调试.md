# UNIAPP调试

## 配置

在调试前可以选择性配置默认域名，配置文件地址：/template/uniapp/pages/config/app.js

```
module.exports = {
    //默认域名,域名格式例如:chat.crmeb.net
    defaultDomainName: '',
    //默认请求方式,可选值：https|http
    defaultRequestType: 'https',
    //默认ws链接方式,可选值：wss|ws
    defaultWsType: 'wss',
    //是否打开自定义配置域名,可选值:true|false;关闭后必须要配置默认域名(defaultDomainName)选项
    isDomainName: true,
}

```

## H5调试
打开项目 `/template/uniapp/` 选择运行中的运行到浏览器，就可以进行调试

![输入图片说明](https://images.gitee.com/uploads/images/2022/0107/171526_0042bd72_1491977.png "屏幕截图.png")

## 真机调试
打开项目 `/template/uniapp/` 选择运行中的运行到手机或者模拟器，就可以进行调试
手机调试可选自定义基座运行，需要先制作自定义基座，制作之前先申请证书
![输入图片说明](https://images.gitee.com/uploads/images/2022/0107/171656_b426f740_1491977.png "屏幕截图.png")
