#!/bin/sh

set -eu

template_path="/usr/local/kong/dev-config/kong.template.yml"
lua_source_path="/usr/local/kong/dev-config/set_authenticated_user_id.lua"
output_path="${KONG_DECLARATIVE_CONFIG:-/tmp/kong.yml}"

require_var() {
  var_name="$1"
  eval "var_value=\${$var_name:-}"

  if [ -z "$var_value" ]; then
    echo "Missing required environment variable: $var_name" >&2
    exit 1
  fi
}

for required_var in \
  KONG_AUTH_SERVICE_URL \
  KONG_INVENTORY_SERVICE_URL \
  KONG_ORDER_SERVICE_URL \
  KONG_JWT_ISSUER \
  KONG_JWT_SECRET \
  KONG_TRUSTED_USER_ID_HEADER
do
  require_var "$required_var"
done

lua_block="$(
  sed "s/__KONG_TRUSTED_USER_ID_HEADER__/${KONG_TRUSTED_USER_ID_HEADER}/g" "$lua_source_path" \
    | awk 'NR == 1 { print; next } { print "                    " $0 }'
)"
LUA_BLOCK="$lua_block"

export LUA_BLOCK

perl -0pe '
  my $content = $_;
  my %replacements = (
    "__KONG_AUTH_SERVICE_URL__" => $ENV{KONG_AUTH_SERVICE_URL},
    "__KONG_INVENTORY_SERVICE_URL__" => $ENV{KONG_INVENTORY_SERVICE_URL},
    "__KONG_ORDER_SERVICE_URL__" => $ENV{KONG_ORDER_SERVICE_URL},
    "__KONG_JWT_ISSUER__" => $ENV{KONG_JWT_ISSUER},
    "__KONG_JWT_SECRET__" => $ENV{KONG_JWT_SECRET},
    "__SET_AUTHENTICATED_USER_ID_LUA__" => $ENV{LUA_BLOCK},
  );

  for my $placeholder (keys %replacements) {
    my $value = $replacements{$placeholder};
    $content =~ s/\Q$placeholder\E/$value/g;
  }

  $_ = $content;
' "$template_path" > "$output_path"

exec /docker-entrypoint.sh kong docker-start
