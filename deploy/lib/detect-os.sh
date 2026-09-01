#!/usr/bin/env bash
# OS gate — Ubuntu 24.04, Debian 12, EL 9+.
set -euo pipefail

detect_os() {
    [[ -r /etc/os-release ]] || { echo "Cannot read /etc/os-release" >&2; return 1; }
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-unknown}"
    OS_VER="${VERSION_ID:-0}"
    OS_MAJOR="${OS_VER%%.*}"
    DISTRO_FAMILY=""
    PKG_MGR=""

    case "$OS_ID" in
        almalinux|rocky|centos|rhel|ol)
            case "$OS_MAJOR" in
                9|10) PKG_MGR="dnf"; DISTRO_FAMILY="el" ;;
                *) echo "Enterprise Linux ${OS_VER} not supported (need 9+)." >&2; return 1 ;;
            esac
            ;;
        debian)
            case "$OS_MAJOR" in
                12|13) PKG_MGR="apt-get"; DISTRO_FAMILY="debian" ;;
                *) echo "Debian ${OS_VER} not supported (need 12+)." >&2; return 1 ;;
            esac
            ;;
        ubuntu)
            case "$OS_VER" in
                24.04) PKG_MGR="apt-get"; DISTRO_FAMILY="ubuntu" ;;
                *) echo "Ubuntu ${OS_VER} not supported (need 24.04)." >&2; return 1 ;;
            esac
            ;;
        *)
            echo "Unsupported distribution: ${OS_ID} ${OS_VER}." >&2
            return 1
            ;;
    esac

    export OS_ID OS_VER OS_MAJOR DISTRO_FAMILY PKG_MGR
    echo "==> OS: ${PRETTY_NAME:-$OS_ID $OS_VER} (${DISTRO_FAMILY}, ${PKG_MGR})"
}
