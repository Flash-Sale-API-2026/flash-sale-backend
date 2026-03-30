kong.service.request.clear_header("__KONG_TRUSTED_USER_ID_HEADER__")

local auth_header = kong.request.get_header("authorization")

if not auth_header then
  return
end

local token = auth_header:match("[Bb]earer%s+(.+)")

if not token then
  return
end

local payload_segment = token:match("^[^.]+%.([^.]+)%.?[^.]*$")

if not payload_segment then
  return
end

payload_segment = payload_segment:gsub("-", "+"):gsub("_", "/")

local remainder = #payload_segment % 4

if remainder > 0 then
  payload_segment = payload_segment .. string.rep("=", 4 - remainder)
end

local decoded = ngx.decode_base64(payload_segment)

if not decoded then
  return
end

local user_id = decoded:match('"sub"%s*:%s*"([^"]+)"')

if not user_id then
  user_id = decoded:match('"sub"%s*:%s*([0-9]+)')
end

if not user_id then
  return
end

kong.service.request.set_header("__KONG_TRUSTED_USER_ID_HEADER__", tostring(user_id))
