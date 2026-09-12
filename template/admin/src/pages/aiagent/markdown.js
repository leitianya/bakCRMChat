// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI Agent 页面用简易 Markdown 渲染
// +----------------------------------------------------------------------

/**
 * 转义 HTML 特殊字符
 * @param {String} s 原始文本
 * @returns {String}
 */
function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/**
 * 行内渲染：粗体/斜体/行内代码/链接
 * @param {String} s 已转义文本
 * @returns {String}
 */
function inline(s) {
    return s
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+?)`/g, '<code>$1</code>')
        .replace(/\[([^\]]+?)\]\((https?:\/\/[^\s)]+?)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
}

/**
 * 简易 Markdown 转 HTML（标题/列表/表格/代码块/引用/分割线），输出前已做 HTML 转义
 * @param {String} md Markdown 文本
 * @returns {String} HTML
 */
export function mdToHtml(md) {
    const lines = String(md || '').split(/\r?\n/);
    let html = '';
    let listTag = '';
    let inCode = false;
    let codeLines = [];
    let tableRows = [];

    const closeList = () => {
        if (listTag) {
            html += '</' + listTag + '>';
            listTag = '';
        }
    };
    const closeTable = () => {
        if (tableRows.length) {
            let table = '<table><thead><tr>';
            tableRows[0].forEach((cell) => {
                table += '<th>' + inline(cell) + '</th>';
            });
            table += '</tr></thead><tbody>';
            tableRows.slice(2).forEach((row) => {
                table += '<tr>';
                row.forEach((cell) => {
                    table += '<td>' + inline(cell) + '</td>';
                });
                table += '</tr>';
            });
            table += '</tbody></table>';
            html += table;
            tableRows = [];
        }
    };
    const closeAll = () => {
        closeList();
        closeTable();
    };
    const splitRow = (line) =>
        line.replace(/^\||\|$/g, '').split('|').map((cell) => cell.trim());

    lines.forEach((rawLine) => {
        const line = rawLine.trim();
        // 代码围栏
        if (/^(```|~~~)/.test(line)) {
            if (inCode) {
                html += '<pre><code>' + escapeHtml(codeLines.join('\n')) + '</code></pre>';
                codeLines = [];
                inCode = false;
            } else {
                closeAll();
                inCode = true;
            }
            return;
        }
        if (inCode) {
            codeLines.push(rawLine);
            return;
        }
        // 表格行（含分隔行）
        if (/^\|.*\|$/.test(line)) {
            closeList();
            tableRows.push(splitRow(line));
            return;
        }
        closeTable();
        if (!line) {
            closeList();
            return;
        }
        if (/^(-{3,}|\*{3,})$/.test(line)) {
            closeList();
            html += '<hr/>';
        } else if (/^#{1,6}\s+/.test(line)) {
            closeList();
            const level = Math.min(line.match(/^#+/)[0].length + 1, 6);
            html += '<h' + level + '>' + inline(line.replace(/^#{1,6}\s+/, '')) + '</h' + level + '>';
        } else if (/^[-*+]\s+/.test(line)) {
            if (listTag !== 'ul') {
                closeList();
                html += '<ul>';
                listTag = 'ul';
            }
            html += '<li>' + inline(line.replace(/^[-*+]\s+/, '')) + '</li>';
        } else if (/^\d+\.\s+/.test(line)) {
            if (listTag !== 'ol') {
                closeList();
                html += '<ol>';
                listTag = 'ol';
            }
            html += '<li>' + inline(line.replace(/^\d+\.\s+/, '')) + '</li>';
        } else if (/^>\s?/.test(line)) {
            closeList();
            html += '<blockquote><p>' + inline(line.replace(/^>\s?/, '')) + '</p></blockquote>';
        } else {
            closeList();
            html += '<p>' + inline(line) + '</p>';
        }
    });
    if (inCode && codeLines.length) {
        html += '<pre><code>' + escapeHtml(codeLines.join('\n')) + '</code></pre>';
    }
    closeAll();
    return html || '<p></p>';
}
