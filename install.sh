#!/bin/sh

set -e

if [ -z "${BIN_DIR}" ]; then
	BIN_DIR=$(pwd)
fi

THE_ARCH_BIN=""
DEST=${BIN_DIR}/mager

OS=$(uname -s)
ARCH=$(uname -m)

if type "tput" >/dev/null 2>&1; then
	bold=$(tput bold || true)
	italic=$(tput sitm || true)
	normal=$(tput sgr0 || true)
fi

case ${OS} in
Linux*)
	case ${ARCH} in
	aarch64)
		THE_ARCH_BIN="mager-linux-aarch64"
		;;
	x86_64)
		THE_ARCH_BIN="mager-linux-x86_64"
		;;
	*)
		THE_ARCH_BIN=""
		;;
	esac
	;;
Darwin*)
	case ${ARCH} in
	arm64)
		THE_ARCH_BIN="mager-macos-aarch64"
		;;
	*)
		THE_ARCH_BIN="mager-macos-x86_64"
		;;
	esac
	;;
Windows | MINGW64_NT*)
	echo "❗ Use WSL to run Mager on Windows: https://learn.microsoft.com/windows/wsl/"
	exit 1
	;;
*)
	THE_ARCH_BIN=""
	;;
esac

if [ -z "${THE_ARCH_BIN}" ]; then
	echo "❗ Mager is not supported on ${OS} and ${ARCH}"
	exit 1
fi

SUDO=""

echo "📦 Downloading ${bold}Mager${normal} for ${OS} (${ARCH}):"

# check if $DEST is writable and suppress an error message
touch "${DEST}" 2>/dev/null

# we need sudo powers to write to DEST
if [ $? -eq 1 ]; then
	echo "❗ You do not have permission to write to ${italic}${DEST}${normal}, enter your password to grant sudo powers"
	SUDO="sudo"
fi

if type "curl" >/dev/null 2>&1; then
	curl -L --progress-bar "https://github.com/praswicaksono/mager-deploy/releases/latest/download/${THE_ARCH_BIN}.tar.gz" -o "${DEST}.tar.gz"
elif type "wget" >/dev/null 2>&1; then
	${SUDO} wget "https://github.com/praswicaksono/mager-deploy/releases/latest/download/${THE_ARCH_BIN}.tar.gz" -O "${DEST}.tar.gz"
else
	echo "❗ Please install ${italic}curl${normal} or ${italic}wget${normal} to download Mager"
	exit 1
fi

${SUDO} tar -xvzf "${DEST}.tar.gz" -C "${BIN_DIR}" && mv "${BIN_DIR}/${THE_ARCH_BIN}" "${DEST}" && rm "${DEST}.tar.gz"
${SUDO} chmod +x "${DEST}"

echo
echo "🥳 Mager downloaded successfully to ${italic}${DEST}${normal}"
echo "🔧 Move the binary to ${italic}/usr/local/bin/${normal} or another directory in your ${italic}PATH${normal} to use it globally:"
echo "   ${bold}sudo mv ${DEST} /usr/local/bin/${normal}"
echo
echo "⭐ If you like Mager, please give it a star on GitHub: ${italic}https://github.com/praswicaksono/mager-deploy${normal}"
