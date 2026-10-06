"""Tiny helpers that emit WordPress core block markup (Gutenberg serialization)."""
import json
from html import escape


def _attrs(d):
    d = {k: v for k, v in d.items() if v not in (None, "", {}, [])}
    return (" " + json.dumps(d, ensure_ascii=False, separators=(",", ":"))) if d else ""


def p(html, cls=None, align=None):
    a = {"className": cls, "align": align}
    c = " ".join(x for x in [f"has-text-align-{align}" if align else None, cls] if x)
    return f'<!-- wp:paragraph{_attrs(a)} -->\n<p{f" class={chr(34)}{c}{chr(34)}" if c else ""}>{html}</p>\n<!-- /wp:paragraph -->'


def h(text, level=2, cls=None, align=None):
    a = {"level": level if level != 2 else None, "className": cls, "textAlign": align}
    c = " ".join(x for x in ["wp-block-heading", f"has-text-align-{align}" if align else None, cls] if x)
    return f'<!-- wp:heading{_attrs(a)} -->\n<h{level} class="{c}">{text}</h{level}>\n<!-- /wp:heading -->'


def group(*inner, cls=None, align=None, tag="div", bg=None, layout="constrained", anchor=None):
    a = {"tagName": tag if tag != "div" else None, "align": align, "className": cls, "anchor": anchor}
    if bg:
        a["style"] = {"background": {"backgroundImage": {"url": bg["url"], "id": bg["id"], "source": "file", "title": ""},
                                     "backgroundSize": "cover", "backgroundPosition": bg.get("pos", "50% 50%")}}
    a["layout"] = {"type": layout}
    c = " ".join(x for x in ["wp-block-group", f"align{align}" if align else None, cls] if x)
    body = "\n\n".join(inner)
    idattr = f' id="{anchor}"' if anchor else ""
    return f'<!-- wp:group{_attrs(a)} -->\n<{tag}{idattr} class="{c}">{body}</{tag}>\n<!-- /wp:group -->'


def columns(*cols, cls=None, align=None):
    a = {"align": align, "className": cls}
    c = " ".join(x for x in ["wp-block-columns", f"align{align}" if align else None, cls] if x)
    return f'<!-- wp:columns{_attrs(a)} -->\n<div class="{c}">' + "\n\n".join(cols) + '</div>\n<!-- /wp:columns -->'


def column(*inner, cls=None):
    a = {"className": cls}
    c = " ".join(x for x in ["wp-block-column", cls] if x)
    return f'<!-- wp:column{_attrs(a)} -->\n<div class="{c}">' + "\n\n".join(inner) + '</div>\n<!-- /wp:column -->'


def buttons(*btns, cls=None):
    a = {"className": cls}
    c = " ".join(x for x in ["wp-block-buttons", cls] if x)
    return f'<!-- wp:buttons{_attrs(a)} -->\n<div class="{c}">' + "\n\n".join(btns) + '</div>\n<!-- /wp:buttons -->'


def button(text, url, outline=False):
    a = {"className": "is-style-outline" if outline else None}
    c = "wp-block-button" + (" is-style-outline" if outline else "")
    return (f'<!-- wp:button{_attrs(a)} -->\n<div class="{c}"><a class="wp-block-button__link wp-element-button" '
            f'href="{escape(url)}">{text}</a></div>\n<!-- /wp:button -->')


def img(m, alt="", cls=None, size="large"):
    a = {"id": m["id"], "sizeSlug": size, "linkDestination": "none", "className": cls}
    c = " ".join(x for x in ["wp-block-image", f"size-{size}", cls] if x)
    return (f'<!-- wp:image{_attrs(a)} -->\n<figure class="{c}"><img src="{m["url"]}" alt="{escape(alt)}" '
            f'class="wp-image-{m["id"]}"/></figure>\n<!-- /wp:image -->')


def gallery(images, cls="rd-gallery"):
    inner = "\n\n".join(img(m, alt) for m, alt in images)
    a = {"linkTo": "none", "className": cls}
    return (f'<!-- wp:gallery{_attrs(a)} -->\n<figure class="wp-block-gallery has-nested-images columns-default is-cropped {cls}">'
            f'{inner}</figure>\n<!-- /wp:gallery -->')


def ul(items, cls=None):
    a = {"className": cls}
    lis = "\n\n".join(f"<!-- wp:list-item -->\n<li>{i}</li>\n<!-- /wp:list-item -->" for i in items)
    return f'<!-- wp:list{_attrs(a)} -->\n<ul class="wp-block-list{(" " + cls) if cls else ""}">{lis}</ul>\n<!-- /wp:list -->'


def table(head, rows, cls=None):
    a = {"className": cls}
    th = "".join(f"<th>{x}</th>" for x in head)
    tb = "".join("<tr>" + "".join(f"<td>{x}</td>" for x in r) + "</tr>" for r in rows)
    c = " ".join(x for x in ["wp-block-table", cls] if x)
    return (f'<!-- wp:table{_attrs(a)} -->\n<figure class="{c}"><table class="has-fixed-layout"><thead><tr>{th}</tr></thead>'
            f'<tbody>{tb}</tbody></table></figure>\n<!-- /wp:table -->')


def details(summary, *inner):
    return (f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{summary}</summary>'
            + "\n\n".join(inner) + '</details>\n<!-- /wp:details -->')


def spacer(px=40):
    return (f'<!-- wp:spacer {{"height":"{px}px"}} -->\n<div style="height:{px}px" aria-hidden="true" '
            f'class="wp-block-spacer"></div>\n<!-- /wp:spacer -->')


def raw(block):
    return block
