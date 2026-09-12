// AI 写作菜单（wangeditor v5 自定义 IButtonMenu）
//
// 点击后通过事件总线通知编辑器组件打开 AI 写作弹窗。
import bus from './bus';

export default class AiMenu {
  constructor() {
    // v5 按钮无 iconSvg 时直接以 title 文本渲染，保持「AI」文字按钮样式
    this.title = 'AI';
    this.tag = 'button';
    // 打开写作弹窗不依赖已有选区，选区为空时也保持可点击
    this.alwaysEnable = true;
  }
  // 菜单点击事件
  exec(editor) {
    bus.$emit('AiWrite', editor);
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
