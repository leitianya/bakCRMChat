// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI Agent 智能体接口（对话 / 模型 / MCP Server / Skill / 自动化任务）
// +----------------------------------------------------------------------

import request from '@/libs/request';
import Setting from '@/setting';
import { getCookies } from '@/libs/util';

/* ---------------- 对话与会话 ---------------- */

export function aiAgentOptionsApi() {
    return request({ url: 'aiagent/options', method: 'get' });
}

export function aiAgentConversationsApi() {
    return request({ url: 'aiagent/conversations', method: 'get' });
}

export function aiAgentMessagesApi(conversationId) {
    return request({ url: `aiagent/messages/${conversationId}`, method: 'get' });
}

export function aiAgentDeleteConversationApi(id) {
    return request({ url: `aiagent/conversation/${id}`, method: 'delete' });
}

/**
 * 修改会话（标题/置顶，title 与 is_pin 至少传一项）
 * @param {Number} id 会话 ID
 * @param {Object} data {title: 新标题，is_pin: -1 不修改 / 0 取消置顶 / 1 置顶}
 */
export function aiAgentUpdateConversationApi(id, data) {
    return request({ url: `aiagent/conversation/${id}`, method: 'put', data });
}

/**
 * AI 流式对话（SSE）：通过 fetch 读取字节流逐块解析，事件实时回调
 * @param {Object} data 对话参数（conversation_id / message / model_id / mcp_server_ids / skill_keys）
 * @param {Function} onEvent 事件回调 function(event)，event = {event: 'start'|'skills'|'reasoning'|'delta'|'replace'|'tool'|'confirm'|'followups'|'done'|'error', ...}
 * @param {Object} [options] 额外选项（signal: AbortSignal，用于手动终止流式输出）
 * @returns {Promise<void>} 流读取完毕后 resolve
 */
export function aiAgentChatStreamApi(data, onEvent, options = {}) {
    return sseRequest('aiagent/chat_stream', data, onEvent, options);
}

/**
 * AI 待确认操作处理（SSE）：approve=按快照执行并继续对话循环 / reject=取消
 * @param {Object} data 参数（conversation_id / message_id / action / answers / custom）
 * @param {Function} onEvent 事件回调，事件类型与 chat_stream 一致
 * @param {Object} [options] 额外选项（signal: AbortSignal）
 * @returns {Promise<void>}
 */
export function aiAgentConfirmStreamApi(data, onEvent, options = {}) {
    return sseRequest('aiagent/confirm_stream', data, onEvent, options);
}

/**
 * SSE POST 通用封装：fetch 读取字节流逐块解析，事件实时回调
 * @param {String} path 接口路径（相对 apiBaseURL）
 * @param {Object} data 请求参数
 * @param {Function} onEvent 事件回调 function(event)，event 为反序列化后的 JSON 对象
 * @param {Object} [options] 额外选项（signal: AbortSignal）
 * @returns {Promise<void>}
 */
export function sseRequest(path, data, onEvent, options = {}) {
    const base = Setting.apiBaseURL.replace(/\/$/, '');
    return fetch(`${base}/${path}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authori-zation': 'Bearer ' + (getCookies('token') || '')
        },
        body: JSON.stringify(data),
        signal: options.signal
    }).then(async (response) => {
        if (!response.ok || !response.body) {
            if (response.status === 401) throw new Error('登录已失效，请重新登录');
            if (response.status === 403) throw new Error('暂无操作权限');
            throw new Error('流式请求失败：HTTP ' + response.status);
        }
        // 解析单个 SSE 事件块：取 data: 行反序列化后回调
        const emitBlock = (block) => {
            const line = block.split('\n').find((item) => item.indexOf('data:') === 0);
            if (!line) return;
            try {
                onEvent(JSON.parse(line.slice(5).trim()));
            } catch (e) {
                // 单个事件解析失败时跳过，不中断整个流
            }
        };
        const reader = response.body.getReader();
        const decoder = new TextDecoder('utf-8');
        let buffer = '';
        for (;;) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            let index;
            while ((index = buffer.indexOf('\n\n')) !== -1) {
                const block = buffer.slice(0, index);
                buffer = buffer.slice(index + 2);
                emitBlock(block);
            }
        }
        // 尾部兜底：流结束时残留 buffer（末尾事件缺空行分隔符）也参与解析，避免静默丢弃
        if (buffer.trim()) emitBlock(buffer);
    });
}

/* ---------------- AI 模型管理 ---------------- */

export function aiModelListApi(params) {
    return request({ url: 'ai/model/list', method: 'get', params });
}

export function aiModelProtocolsApi() {
    return request({ url: 'ai/model/protocols', method: 'get' });
}

/**
 * 一号通凭证配置状态（模型管理一键配置入口）
 * @returns {Promise} {configured: 1 已配置 | 0 未配置}
 */
export function aiModelYihaotongStatusApi() {
    return request({ url: 'ai/model/yihaotong_status', method: 'get' });
}

export function aiModelDetailApi(id) {
    return request({ url: `ai/model/detail/${id}`, method: 'get' });
}

export function aiModelSaveApi(data) {
    return request({ url: 'ai/model/save', method: 'post', data });
}

export function aiModelTestApi(data) {
    return request({ url: 'ai/model/test', method: 'post', data });
}

export function aiModelUpdateApi(id, data) {
    return request({ url: `ai/model/update/${id}`, method: 'put', data });
}

export function aiModelDeleteApi(id) {
    return request({ url: `ai/model/delete/${id}`, method: 'delete' });
}

export function aiModelStatusApi(id, status) {
    return request({ url: `ai/model/set_status/${id}`, method: 'put', data: { status } });
}

export function aiModelSetDefaultApi(id) {
    return request({ url: `ai/model/set_default/${id}`, method: 'put' });
}

/* ---------------- MCP Server 管理 ---------------- */

export function aiMcpServerListApi() {
    return request({ url: 'aiagent/mcp_servers', method: 'get' });
}

export function aiMcpServerDetailApi(id) {
    return request({ url: `aiagent/mcp_server/${id}`, method: 'get' });
}

export function aiMcpServerSaveApi(data, id = 0) {
    return request({
        url: id ? `aiagent/mcp_server/${id}` : 'aiagent/mcp_server',
        method: id ? 'put' : 'post',
        data
    });
}

export function aiMcpServerDeleteApi(id) {
    return request({ url: `aiagent/mcp_server/${id}`, method: 'delete' });
}

export function aiMcpServerStatusApi(id, status) {
    return request({ url: `aiagent/mcp_server/${id}/status`, method: 'put', data: { status } });
}

export function aiMcpServerSyncApi(id) {
    return request({ url: `aiagent/mcp_server/${id}/sync`, method: 'post' });
}

export function aiMcpServerTestApi(id) {
    return request({ url: `aiagent/mcp_server/${id}/test`, method: 'post' });
}

/* ---------------- Skill 管理 ---------------- */

export function aiSkillListApi() {
    return request({ url: 'aiagent/skills', method: 'get' });
}

export function aiSkillDetailApi(key) {
    return request({ url: `aiagent/skill/${key}`, method: 'get' });
}

export function aiSkillSaveApi(data) {
    return request({ url: 'aiagent/skill', method: 'post', data });
}

export function aiSkillUpdateApi(key, data) {
    return request({ url: `aiagent/skill/${key}`, method: 'put', data });
}

export function aiSkillDeleteApi(key) {
    return request({ url: `aiagent/skill/${key}`, method: 'delete' });
}

export function aiSkillStatusApi(key, status) {
    return request({ url: `aiagent/skill/${key}/status`, method: 'put', data: { status } });
}

/* ---------------- Skill 附属资料管理 ---------------- */

export function aiSkillAttachmentListApi(key) {
    return request({ url: `aiagent/skill/${key}/attachments`, method: 'get' });
}

export function aiSkillAttachmentReadApi(key, file) {
    return request({ url: `aiagent/skill/${key}/attachment`, method: 'get', params: { file } });
}

export function aiSkillAttachmentSaveApi(key, data) {
    return request({ url: `aiagent/skill/${key}/attachment`, method: 'post', data });
}

export function aiSkillAttachmentDeleteApi(key, file) {
    return request({ url: `aiagent/skill/${key}/attachment`, method: 'delete', params: { file } });
}

/* ---------------- AI 自动化任务管理 ---------------- */

export function aiTaskListApi(params) {
    return request({ url: 'aiagent/tasks', method: 'get', params });
}

export function aiTaskSaveApi(data) {
    const id = data.id || 0;
    return request({
        url: id ? `aiagent/task/${id}` : 'aiagent/task',
        method: id ? 'put' : 'post',
        data
    });
}

export function aiTaskDetailApi(id) {
    return request({ url: `aiagent/task/${id}`, method: 'get' });
}

export function aiTaskDeleteApi(id) {
    return request({ url: `aiagent/task/${id}`, method: 'delete' });
}

export function aiTaskStatusApi(id, status) {
    return request({ url: `aiagent/task/${id}/status`, method: 'put', data: { status } });
}

export function aiTaskTriggerApi(id) {
    return request({ url: `aiagent/task/${id}/run`, method: 'post' });
}

export function aiTaskRunsApi(id, params) {
    return request({ url: `aiagent/task/${id}/runs`, method: 'get', params });
}

export function aiTaskRunListApi(params) {
    return request({ url: 'aiagent/task_runs', method: 'get', params });
}

export function aiTaskRunDetailApi(runId) {
    return request({ url: `aiagent/task_run/${runId}`, method: 'get' });
}
