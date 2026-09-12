// 源码视图按钮（wangeditor v5 自定义 IButtonMenu）
// 点击后通过事件总线通知编辑器组件切换可视化/源码（HTML）视图
import bus from './bus';

export default class HtmlMenu {
  constructor() {
    // 无 iconSvg 时直接以 title 文本渲染，保持「HTML」文字按钮样式
    this.title = 'HTML';
    this.tag = 'button';
    // 切换源码视图不依赖已有选区，选区为空时也保持可点击
    this.alwaysEnable = true;
  }
  // 菜单点击事件
  exec(editor) {
    bus.$emit('Html', editor);
  }
  getValue() {
    return '';
  }
  isActive() {
    return false;
  }
  isDisabled() {
    return false;
  }
}
