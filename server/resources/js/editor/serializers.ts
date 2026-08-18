/**
 * Deterministic serializers that derive markdown and plain text from the
 * canonical ProseMirror/Tiptap JSON document (SRS §10.2: markdown/plain text
 * are derived formats for interoperability/search).
 *
 * These are pure functions over EditorDocument — no editor engine involved —
 * so they are cheap, deterministic, and unit-testable in isolation.
 */

import type { DerivedDocument, EditorDocument, EditorNode } from './types';

function blockContent(node: EditorNode): EditorNode[] {
    return node.content ?? [];
}

function isText(node: EditorNode): boolean {
    return node.type === 'text';
}

function inlineText(node: EditorNode): string {
    if (!isText(node)) {
        return '';
    }
    let text = node.text ?? '';
    for (const mark of node.marks ?? []) {
        switch (mark.type) {
            case 'bold':
                text = `**${text}**`;
                break;
            case 'italic':
                text = `*${text}*`;
                break;
            case 'strike':
                text = `~~${text}~~`;
                break;
            case 'code':
                text = `\`${text}\``;
                break;
            case 'link': {
                const href = (mark.attrs?.href as string | undefined) ?? '';
                text = `[${text}](${href})`;
                break;
            }
        }
    }
    return text;
}

function inlineMarkdown(node: EditorNode): string {
    return blockContent(node)
        .map((child) => (child.type === 'hardBreak' ? '  \n' : inlineText(child)))
        .join('');
}

function plainTextOf(node: EditorNode): string {
    if (isText(node)) {
        return node.text ?? '';
    }
    return blockContent(node).map(plainTextOf).join('');
}

function headingMarkdown(node: EditorNode): string {
    const level = node.attrs?.level ?? 1;
    const hashes = '#'.repeat(Math.max(1, Math.min(6, Number(level))));
    return `${hashes} ${inlineMarkdown(node)}`;
}

function listItemMarkdown(node: EditorNode, indent: number, marker: string): string {
    const children = blockContent(node);
    const firstPara = children[0];
    const rest = children.slice(1);
    const first = firstPara ? inlineMarkdown(firstPara) : '';
    const indentStr = '  '.repeat(indent);
    const lines: string[] = [`${indentStr}${marker} ${first}`];
    for (const child of rest) {
        const isList = child.type === 'bulletList'
            || child.type === 'orderedList'
            || child.type === 'taskList';
        const rendered = markdownBlock(child, isList ? indent + 1 : indent);
        lines.push(isList ? rendered : `${indentStr}  ${rendered}`);
    }
    return lines.join('\n');
}

function taskItemMarkdown(node: EditorNode, indent: number): string {
    const checked = node.attrs?.checked === true;
    const marker = checked ? '- [x]' : '- [ ]';
    return listItemMarkdown(node, indent, marker);
}

function markdownBlock(node: EditorNode, indent = 0): string {
    switch (node.type) {
        case 'paragraph':
            return inlineMarkdown(node);
        case 'heading':
            return headingMarkdown(node);
        case 'blockquote': {
            const inner = blockContent(node).map((c) => markdownBlock(c, indent)).join('\n');
            return inner
                .split('\n')
                .map((line) => (line.trim() ? `> ${line}` : '>'))
                .join('\n');
        }
        case 'codeBlock': {
            const language = node.attrs?.language as string | undefined;
            const lang = language ? `${language}` : '';
            const code = blockContent(node).map((c) => c.text ?? '').join('\n');
            return `\`\`\`${lang}\n${code}\n\`\`\``;
        }
        case 'bulletList': {
            const items = blockContent(node);
            if (items.length === 0) {
                return '';
            }
            return items.map((item) => listItemMarkdown(item, indent, '-')).join('\n');
        }
        case 'orderedList': {
            const items = blockContent(node);
            if (items.length === 0) {
                return '';
            }
            const start = node.attrs?.start ?? 1;
            return items
                .map((item, i) => listItemMarkdown(item, indent, `${Number(start) + i}.`))
                .join('\n');
        }
        case 'taskList': {
            const items = blockContent(node);
            if (items.length === 0) {
                return '';
            }
            return items.map((item) => taskItemMarkdown(item, indent)).join('\n');
        }
        case 'horizontalRule':
            return '---';
        case 'hardBreak':
            return '  \n';
        default:
            return blockContent(node).map((c) => markdownBlock(c, indent)).join('\n');
    }
}

/** Derive markdown from a canonical document. Deterministic. */
export function documentToMarkdown(document: EditorDocument): string {
    const blocks = (document.content ?? []).map((node) => markdownBlock(node, 0));
    return blocks.join('\n\n').replace(/\n{3,}/g, '\n\n').trimEnd() + '\n';
}

/** Derive plain text from a canonical document. Deterministic. */
export function documentToPlainText(document: EditorDocument): string {
    const blocks = (document.content ?? []).map((node) => plainTextOf(node).trim());
    return blocks.filter((block) => block.length > 0).join('\n').trimEnd() + '\n';
}

/** Derive both derived formats. */
export function derive(document: EditorDocument): DerivedDocument {
    return {
        markdown: documentToMarkdown(document),
        plainText: documentToPlainText(document),
    };
}