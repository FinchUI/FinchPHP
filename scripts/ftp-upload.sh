#!/bin/bash
# FTP 单文件/目录上传工具
# 用法:
#   ./ftp-upload.sh <本地文件绝对路径>     - 上传单个文件
#   ./ftp-upload.sh sync                   - 全量同步整个项目
#
# 配置来自 .vscode/sftp.json

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST="192.168.10.247"
PORT="21"
USER="finchftp"
PASS="xGBcrH6PKp9r"
REMOTE_PATH="/fp.fui.show"

# 忽略的目录/文件
IGNORE_PATTERNS=(".vscode" ".git" ".codebuddy" "node_modules" ".DS_Store" ".gitignore" "showcase-import.json")

upload_file() {
    local local_file="$1"
    local relative="${local_file#$PROJECT_ROOT/}"

    # 检查忽略规则
    for pattern in "${IGNORE_PATTERNS[@]}"; do
        if [[ "$relative" == *"$pattern"* ]]; then
            return 0
        fi
    done

    # 创建远程目录并上传
    local remote_dir
    remote_dir=$(dirname "$relative")
    echo -n "  ↑ $relative ... "

    result=$(curl -s --ftp-create-dirs \
        -T "$local_file" \
        "ftp://$USER:$PASS@$HOST:$PORT$REMOTE_PATH/$relative" 2>&1)

    if [ $? -eq 0 ]; then
        echo "OK"
    else
        echo "FAIL: $result"
    fi
}

sync_all() {
    echo "🔄 全量同步 $PROJECT_ROOT → $HOST$REMOTE_PATH"
    local count=0
    local total

    total=$(find "$PROJECT_ROOT" -type f \
        ! -path "*/.git/*" \
        ! -path "*/.vscode/*" \
        ! -path "*/.codebuddy/*" \
        ! -path "*/node_modules/*" \
        ! -name ".DS_Store" \
        ! -name "showcase-import.json" \
        ! -name "README.md" \
        | wc -l | tr -d ' ')

    echo "  共 $total 个文件待同步"

    while IFS= read -r file; do
        upload_file "$file"
        count=$((count + 1))
    done < <(find "$PROJECT_ROOT" -type f \
        ! -path "*/.git/*" \
        ! -path "*/.vscode/*" \
        ! -path "*/.codebuddy/*" \
        ! -path "*/node_modules/*" \
        ! -name ".DS_Store" \
        ! -name "showcase-import.json" \
        ! -name "README.md")

    echo "✅ 完成，已同步 $count/$total 个文件"
}

# 主逻辑
if [ "$1" = "sync" ]; then
    sync_all
elif [ -n "$1" ]; then
    # 支持多文件参数
    for file in "$@"; do
        if [ -f "$file" ]; then
            upload_file "$file"
        else
            echo "⚠ 文件不存在: $file"
        fi
    done
else
    echo "用法: $0 <文件路径...> | sync"
    echo "  sync  - 全量同步项目"
    echo "  <文件> - 上传指定文件（支持多个）"
    exit 1
fi
