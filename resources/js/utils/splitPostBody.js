/**
 * Split sanitized post HTML into intro + h2 sections.
 * @param {string} html
 * @returns {{ introHtml: string, sections: Array<{ id: string, title: string, html: string }> }}
 */
export function splitPostBody(html) {
  const source = String(html || '').trim();
  if (!source) {
    return { introHtml: '', sections: [] };
  }

  if (typeof DOMParser === 'undefined') {
    return { introHtml: source, sections: [] };
  }

  const doc = new DOMParser().parseFromString(`<div id="root">${source}</div>`, 'text/html');
  const root = doc.getElementById('root');
  if (!root) {
    return { introHtml: source, sections: [] };
  }

  const nodes = [...root.childNodes];
  const introNodes = [];
  /** @type {Array<{ id: string, title: string, nodes: ChildNode[] }>} */
  const sectionBuckets = [];
  let current = null;
  const usedIds = new Set();

  for (const node of nodes) {
    if (node.nodeType === Node.ELEMENT_NODE && /** @type {Element} */ (node).tagName === 'H2') {
      const title = (node.textContent || '').trim() || 'Section';
      current = {
        id: uniqueId(slugify(title), usedIds),
        title,
        nodes: [],
      };
      sectionBuckets.push(current);
      continue;
    }

    if (current) {
      current.nodes.push(node);
    } else {
      introNodes.push(node);
    }
  }

  return {
    introHtml: serializeNodes(introNodes),
    sections: sectionBuckets.map((section) => ({
      id: section.id,
      title: section.title,
      html: serializeNodes(section.nodes),
    })),
  };
}

function serializeNodes(nodes) {
  return nodes
    .map((node) => {
      if (node.nodeType === Node.ELEMENT_NODE) {
        return /** @type {Element} */ (node).outerHTML;
      }
      if (node.nodeType === Node.TEXT_NODE) {
        const text = (node.textContent || '').trim();
        return text ? `<p>${escapeHtml(text)}</p>` : '';
      }
      return '';
    })
    .filter(Boolean)
    .join('');
}

function slugify(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 48) || 'section';
}

function uniqueId(base, usedIds) {
  let id = base;
  let n = 2;
  while (usedIds.has(id)) {
    id = `${base}-${n}`;
    n += 1;
  }
  usedIds.add(id);
  return id;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
