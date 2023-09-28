#!/usr/bin/env bash

# Adapted from https://github.com/Automattic/jetpack/blob/b3179d4347dcf73269985e1801b61bf53fbc441a/tools/replace-next-version-tag.sh

set -eo pipefail

BASE=$(cd $(dirname "${BASH_SOURCE[0]}")/.. && pwd)
. "$BASE/scripts/includes/chalk-lite.sh"
. "$BASE/scripts/includes/proceed_p.sh"

# Print help and exit.
function usage {
	cat <<-'EOH'
		usage: $0 [-v] <version>

		Replace the `$$next-version$$` token in doc tags with the specified version.
		Recognized patterns:
		 - `@since $$next-version$$`
		 - `@deprecated $$next-version$$`
		 - `@deprecated since $$next-version$$`
		 - `_deprecated_function( ..., 'prefix-$$next-version$$' )`
		   Other WordPress deprecation functions also work. The call must be on one
		   line, indented with tabs, and the '$$next-version$$' token must be in a
		   single-quoted string.
	EOH
	exit 1
}

if [[ $# -eq 0 ]]; then
	usage
fi

# Sets options.
VERBOSE=
while getopts ":vh" opt; do
	case ${opt} in
		v)
			if [[ -n "$VERBOSE" ]]; then
				VERBOSE="${VERBOSE}v"
			else
				VERBOSE="-v"
			fi
			;;
		h)
			usage
			;;
		:)
			die "Argument -$OPTARG requires a value."
			;;
		?)
			error "Invalid argument: -$OPTARG"
			echo ""
			usage
			;;
	esac
done
shift "$(($OPTIND - 1))"

if [[ -z "$VERBOSE" ]]; then
	function debug {
		:
	}
fi

# Determine the version
[[ -z "$1" ]] && die "A version must be specified."
VERSION="$1"
if ! grep -E -q '^[0-9]+(\.[0-9]+)+(-(a|alpha|beta)([-.]?[0-9]+)?)?$' <<<"$VERSION"; then
	proceed_p "Version $VERSION does not seem to be a valid version number." "Continue?"
fi
VE=$(sed 's/[&\\/]/\\&/g' <<<"$VERSION")

cd "$BASE"
EXIT=0
for FILE in $(git ls-files); do
	[ "$FILE" == "scripts/replace-next-version-tag.sh" ] && continue;
	grep -F -q '$$next-version$$' "$FILE" 2>/dev/null || continue
	debug "Processing $FILE"

	sed -i.bak -E -e 's!\$\$next-version\$\$!'"$VE"'!g' "$FILE"
	rm "$FILE.bak" # We need a backup file because macOS requires it.

	if grep -F -q '$$next-version$$' "$FILE"; then
		EXIT=1
		while IFS=':' read -r LINE DUMMY; do
			if [[ -n "$CI" ]]; then
				echo "::error file=$FILE,line=$LINE::"'Unexpected `$$next-version$$` token.'
			else
				error "$FILE:$LINE:"' Unexpected `$$next-version$$` token.'
			fi
		done < <( grep --line-number -F '$$next-version$$' "$FILE" || echo "" )
	fi
done

exit $EXIT
