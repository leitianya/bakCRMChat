// wangeditor v5 自定义菜单注册（模块导入即执行；重复注册吞掉 Duplicated key 报错）
import { Boot } from '@wangeditor/editor';
import AiMenu from './ai';
import HtmlMenu from './html';
import ImageMenu from './imageMenu';

const menuConfigs = [
  { key: 'alertHtml', factory: () => new HtmlMenu() },
  { key: 'uploadImgMenuKey', factory: () => new ImageMenu() },
  { key: 'aiMenuKey', factory: () => new AiMenu() },
];

menuConfigs.forEach((menuConfig) => {
  try {
    Boot.registerMenu(menuConfig);
  } catch (error) {
    if (!(error instanceof Error) || error.message.indexOf(`Duplicated key '${menuConfig.key}'`) === -1) throw error;
  }
});
