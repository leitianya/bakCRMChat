#!/bin/bash
# 将本目录(docs/)内容同步推送到 Gitee 官方 CRMChat Wiki
#
# 用法:
#   ./sync-to-wiki.sh
#
# 说明:
# - 推送凭据使用本机 git 钥匙串(osxkeychain)中已保存的 Gitee 账号(sugar1569)，
#   不要把密码写入本仓库或任何文件（见 .crmchat/AGENTS.md 第 9 条）
# - 排除项: README.md(本地索引页)、本脚本自身、.DS_Store
# - 采用镜像式同步(rsync --delete): 本地 docs/ 为唯一事实来源；
#   若上游 wiki 新增了页面，请先手动同步回本地 docs/ 再推送，否则会在 wiki 上被删除
set -euo pipefail

WIKI_URL="${WIKI_URL:-https://gitee.com/ZhongBangKeJi/CRMChat.wiki.git}"
BRANCH="${BRANCH:-master}"
DOCS_DIR="$(cd "$(dirname "$0")" && pwd)"
WORK_DIR="${WIKI_WORK_DIR:-$HOME/.cache/crmchat-wiki-sync}"

# 1. 准备 wiki 工作副本（已存在则先更新到最新）
if [ -d "$WORK_DIR/.git" ]; then
    git -C "$WORK_DIR" pull --ff-only origin "$BRANCH"
else
    mkdir -p "$(dirname "$WORK_DIR")"
    git clone -q "$WIKI_URL" "$WORK_DIR"
fi

# 2. 镜像同步 docs/ -> wiki 工作副本
# 注意: --exclude .git 必须保留, 否则 --delete 会把工作副本的 .git 目录删掉
rsync -a --delete \
    --exclude .git \
    --exclude README.md \
    --exclude sync-to-wiki.sh \
    --exclude .DS_Store \
    "$DOCS_DIR/" "$WORK_DIR/"

# 3. 提交并推送（无变更则跳过）
cd "$WORK_DIR"
if ! git rev-parse --is-inside-work-tree &>/dev/null; then
    echo "错误: wiki 工作副本异常（缺少 .git），请删除 $WORK_DIR 后重试" >&2
    exit 1
fi
if [ -z "$(git status --porcelain)" ]; then
    echo "wiki 已是最新，无需推送"
    exit 0
fi
git add -A
git commit -m "docs: 同步项目文档更新 $(date +%Y-%m-%d)"
git push origin "$BRANCH"
echo "✅ 已推送到 $WIKI_URL ($BRANCH)"
