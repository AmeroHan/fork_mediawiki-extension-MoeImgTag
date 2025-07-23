local p = {}

function p.extractAttrsFromFrame(frame)
  local args = frame.args
  local attrs = {}
  local validKeys = {
    'src', 'file', 'alt', 'width', 'height', 'class', 'style', 'title', 'loading',
  }

  -- 遍历 args，提取有效的属性
  for key, value in pairs(args) do
    if value ~= '' and value ~= nil then
      -- 检查 key 是否在有效的属性列表中
      local isValid = false
      for _, validKey in ipairs(validKeys) do
        if key == validKey then
          isValid = true
          break
        end
      end
      if isValid then
        -- 如果是有效的属性，则添加到 attrs 中
        attrs[key] = value
      end
    end
  end

  -- 如果有 file，将 src 替换为文件链接
  if attrs.file and attrs.file ~= '' then
    -- 使用 frame 的 callParserFunction 方法获取文件链接
    attrs.src = frame:callParserFunction('filepath', { attrs.file })
    -- 删除 file 属性，因为已经用 src 替换了
    attrs.file = nil
  end

  -- 替换 src 中可能存在的特殊字符
  if attrs.src then
    attrs.src = string.gsub(attrs.src, '&#58;//', '://')
  end

  return attrs
end

function p.extensionTag(frame)
  local attrs = p.extractAttrsFromFrame(frame)
  return frame:extensionTag('img', '', attrs)
end

function p.callParserFunction(frame)
  local attrs = p.extractAttrsFromFrame(frame)

  if attrs.src ~= nil and attrs.src ~= '' then
    attrs[1] = attrs.src
    attrs.src = nil
  end

  return frame:callParserFunction('#img', attrs)
end

function p.printTag(frame)
  local attrs = p.extractAttrsFromFrame(frame)
  local attrStr = ''
  for key, value in pairs(attrs) do
    if value ~= nil and value ~= '' then
      local strippedValue = string.gsub(value, '"', '&quot;') -- 转义双引号
      attrStr = attrStr .. key .. '="' .. strippedValue .. '" '
    end
  end

  return '<img ' .. attrStr .. '/>'
end

function p.preprocessTag(frame)
  local str = p.printTag(frame)
  return frame:preprocess(str)
end

function p.printFunction(frame)
  local attrs = p.extractAttrsFromFrame(frame)
  local attrStr = ''
  for key, value in pairs(attrs) do
    if value ~= nil and value ~= '' then
      -- 转义等号和竖线
      local strippedValue = string.gsub(value, '[=|]', function(c)
        return (c == '=') and '&#61;' or '&#124;'
      end)
      attrStr = attrStr .. key .. '=' .. strippedValue
    end
  end

  return '{{#img:|' .. attrStr .. '}}'
end

function p.preprocessFunction(frame)
  local str = p.printFunction(frame)
  return frame:preprocess(str)
end

return p
