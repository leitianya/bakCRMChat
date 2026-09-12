// 组件内部事件总线：自定义工具栏菜单（AI/图片/源码）与编辑器实例间的解耦通信
// 独立于全局 bus，避免同页多个编辑器实例互相干扰（实例归属由编辑器组件自行判断）
import Vue from 'vue';

export default new Vue();
