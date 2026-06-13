#!/bin/bash
# FTP 批量上传工具（单连接复用，高速）
# 用法:
#   ./ftp-upload.sh <文件1> [文件2] ...    - 上传指定文件
#   ./ftp-upload.sh sync                   - 全量同步项目

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST="192.168.10.247"
PORT="21"
USER="finchftp"
PASS="xGBcrH6PKp9r"
REMOTE="/fp.fui.show"
BASE="ftp://$USER:$PASS@$HOST:$PORT$REMOTE"

IGNORE_PATTERNS=(".git" ".vscode" ".codebuddy" "node_modules" ".DS_Store" ".gitignore" "showcase-import.json" "README.md")

# 用 curl 单连接批量上传
batch_upload() {
    local args=()
    local count=0

    for local_file in "$@"; do
        local relative="${local_file#$PROJECT_ROOT/}"
        # 检查忽略
        local skip=0
        for pattern in "${IGNORE_PATTERNS[@]}"; do
            if [[ "$relative" == *"$pattern"* ]]; then skip=1; break; fi
        done
        [ "$skip" = "1" ] && continue

        local dir
        dir=$(dirname "$relative")
        # curl 批量模式：成对 -T file URL
        args+=(-T "$PROJECT_ROOT/$relative" "$BASE/$relative")
        count=$((count + 1))
    done

    if [ "$count" -eq 0 ]; then
        echo "没有文件需要上传"
        return
    fi

    echo "⬆ 上传 $count 个文件（单连接复用）..."
    # --ftp-create-dirs: 自动创建远程目录
    # --ftp-pasv: 被动模式，避免主动模式的反向连接问题
    # curl 对同一主机的多个 URL 自动复用连接
    curl -s --ftp-create-dirs --ftp-pasv "${args[@]}" && echo "✅ 全部完成" || echo "❌ 部分失败"
}

sync_all() {
    echo "🔄 全量同步 $PROJECT_ROOT → $HOST$REMOTE"
    local files=()
    while IFS= read -r f; do
        files+=("$f")
    done < <(find "$PROJECT_ROOT" -type f \
        ! -path "*/.git/*" \
        ! -path "*/.vscode/*" \
        ! -path "*/.codebuddy/*" \
        ! -path "*/node_modules/*" \
        ! -name ".DS_Store" \
        ! -name "showcase-import.json" \
        ! -name "README.md")

    local total=${#files[@]}
    echo "  共 $total 个文件"

    # 分批上传，每批 30 个文件（避免命令行过长）
    local batch_size=30
    local i=0
    while [ $i -lt $total ]; do
        local batch=()
        local end=$((i + batch_size))
        [ $end -gt $total ] && end=$total
        echo "  批次 $((i/batch_size + 1)): 文件 $((i+1))-$end"
        for ((j=i; j<end; j++)); do
            batch+=("${files[$j]}")
        done
        batch_upload "${batch[@]}"
        i=$end
    done
}

# 主逻辑
if [ "$1" = "sync" ]; then
    sync_all
elif [ -n "$1" ]; then
    # 将相对路径转为绝对路径
    local_args=()
    for f in "$@"; do
        if [[ "$f" != /* ]]; then
            f="$PROJECT_ROOT/$f"
        fi
        local_args+=("$f")
    done
    batch_upload "${local_args[@]}"
else
    echo "用法: $0 <文件路径...> | sync"
    echo "  sync       - 全量同步项目"
    echo "  <文件>...  - 上传指定文件（支持多个，可用相对路径）"
    exit 1
fi
