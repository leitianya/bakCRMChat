// 图片按钮（wangeditor v5 自定义 IButtonMenu）
// 点击后通过事件总线通知编辑器组件打开项目统一的图片素材库弹窗
import bus from './bus';

const IMAGE_SVG =
  '<svg viewBox="0 0 1024 1024"><path d="M864 128H160c-17.7 0-32 14.3-32 32v704c0 17.7 14.3 32 32 32h704c17.7 0 32-14.3 32-32V160c0-17.7-14.3-32-32-32z m-40 696H200V296h624v528zM304 448c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64z m-40 280h496l-152-280-124 176-84-112-136 216z"></path></svg>';

export default class ImageMenu {
  constructor() {
    this.title = '图片';
    this.iconSvg = IMAGE_SVG;
    this.tag = 'button';
    // 打开素材库弹窗不依赖已有选区，选区为空时也保持可点击
    this.alwaysEnable = true;
  }
  // 菜单点击事件
  exec(editor) {
    bus.$emit('UploadImg', editor);
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
