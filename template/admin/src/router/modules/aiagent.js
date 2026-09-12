// +---------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +---------------------------------------------------------------------
// | AI Agent 智能体 路由模块
// +---------------------------------------------------------------------

import BasicLayout from '@/components/main'

const meta = {
  auth: true
}

const pre = 'aiagent_'

export default {
  path: '/admin/aiagent',
  name: 'aiagent',
  header: 'aiagent',
  redirect: {
    name: `${pre}index`
  },
  component: BasicLayout,
  children: [
    {
      path: 'index',
      name: `${pre}index`,
      meta: {
        ...meta,
        title: 'AI Agent'
      },
      component: () => import('@/pages/aiagent/index')
    }
  ]
}
