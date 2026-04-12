<?php
/**
 * 哔哩哔哩富文本节点 → HTML（表情 WebP / picture 等与动态页一致）
 */
class BilibiliRichText {
    /**
     * 规范化表情资源 URL（协议相对补全为 https）
     */
    public static function normalizeEmojiMediaUrl($url) {
        if (!is_string($url)) {
            return '';
        }
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        return $url;
    }

    /**
     * 收藏集等表情常同时返回 webp_url（体积小）与 icon_url（png）。
     * 优先 WebP：有 webp 且有另一路回退时用 <picture>；仅 webp 或仅 png/gif 则用单张 <img>。
     */
    public static function buildEmojiImgHtml(array $emoji, array $node) {
        $webp = self::normalizeEmojiMediaUrl($emoji['webp_url'] ?? '');
        $icon = self::normalizeEmojiMediaUrl($emoji['icon_url'] ?? '');
        $gif = self::normalizeEmojiMediaUrl($emoji['gif_url'] ?? '');

        $fallback = '';
        if ($icon !== '') {
            $fallback = $icon;
        } elseif ($gif !== '') {
            $fallback = $gif;
        }

        $alt_text = $emoji['text'] ?? ($node['text'] ?? '[表情]');
        $alt_esc = htmlspecialchars($alt_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $commonImgAttrs = 'alt="' . $alt_esc . '" class="bili-emote" referrerpolicy="no-referrer" style="display:inline-block;vertical-align:middle;max-height:40px;max-width:120px;margin:0 2px;user-select:none;-webkit-user-select:none;-moz-user-select:none;"';

        if ($webp !== '' && $fallback !== '' && $webp !== $fallback) {
            $w = htmlspecialchars($webp, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f = htmlspecialchars($fallback, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<picture style="display:inline-block;vertical-align:middle;line-height:0;">'
                . '<source srcset="' . $w . '" type="image/webp">'
                . '<img src="' . $f . '" ' . $commonImgAttrs . ' /></picture>';
        }

        $single = $webp !== '' ? $webp : ($icon !== '' ? $icon : $gif);
        if ($single === '') {
            return '';
        }
        $src_esc = htmlspecialchars($single, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<img src="' . $src_esc . '" ' . $commonImgAttrs . ' />';
    }

    /**
     * 富文本节点 → HTML 片段（与 BilibiliDynamic 动态正文一致）
     *
     * @param array|null $rich_text_nodes
     * @return string
     */
    public static function richTextNodesToHtml($rich_text_nodes) {
        if (!is_array($rich_text_nodes)) {
            return '';
        }
        $text = '';
        foreach ($rich_text_nodes as $node) {
            $type = $node['type'] ?? '';
            if ($type === 'RICH_TEXT_NODE_TYPE_EMOJI' && isset($node['emoji']) && is_array($node['emoji'])) {
                $emoji = $node['emoji'];
                $imgHtml = self::buildEmojiImgHtml($emoji, $node);
                if ($imgHtml !== '') {
                    $text .= $imgHtml;
                } elseif (isset($emoji['text'])) {
                    $text .= $emoji['text'];
                } elseif (isset($node['text'])) {
                    $text .= $node['text'];
                }
            } elseif (isset($node['text'])) {
                $text .= $node['text'];
            }
        }
        return $text;
    }

    /**
     * 纯文本扁平（表情用文案占位），用于无 summary.text 时的布局估算等
     *
     * @param array|null $rich_text_nodes
     * @return string
     */
    public static function richTextNodesToPlain($rich_text_nodes) {
        if (!is_array($rich_text_nodes)) {
            return '';
        }
        $out = '';
        foreach ($rich_text_nodes as $node) {
            $type = $node['type'] ?? '';
            if ($type === 'RICH_TEXT_NODE_TYPE_EMOJI' && isset($node['emoji']) && is_array($node['emoji'])) {
                $emoji = $node['emoji'];
                if (isset($emoji['text'])) {
                    $out .= $emoji['text'];
                } elseif (isset($node['text'])) {
                    $out .= $node['text'];
                }
            } elseif (isset($node['text'])) {
                $out .= $node['text'];
            }
        }
        return $out;
    }
}
