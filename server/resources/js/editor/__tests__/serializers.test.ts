import { describe, expect, it } from 'vitest';
import { documentToMarkdown, documentToPlainText } from '../serializers';
import type { EditorDocument } from '../types';

function doc(content: EditorDocument['content']): EditorDocument {
    return { type: 'doc', content };
}

describe('documentToMarkdown', () => {
    it('returns empty document for no content', () => {
        expect(documentToMarkdown(doc(undefined))).toBe('\n');
    });

    it('serializes a paragraph', () => {
        expect(
            documentToMarkdown(
                doc([{ type: 'paragraph', content: [{ type: 'text', text: 'Hello world' }] }]),
            ),
        ).toBe('Hello world\n');
    });

    it('separates blocks with blank lines', () => {
        expect(
            documentToMarkdown(
                doc([
                    { type: 'paragraph', content: [{ type: 'text', text: 'One' }] },
                    { type: 'paragraph', content: [{ type: 'text', text: 'Two' }] },
                ]),
            ),
        ).toBe('One\n\nTwo\n');
    });

    it('serializes headings with levels', () => {
        expect(
            documentToMarkdown(
                doc([
                    { type: 'heading', attrs: { level: 1 }, content: [{ type: 'text', text: 'H1' }] },
                    { type: 'heading', attrs: { level: 3 }, content: [{ type: 'text', text: 'H3' }] },
                ]),
            ),
        ).toBe('# H1\n\n### H3\n');
    });

    it('serializes inline marks', () => {
        expect(
            documentToMarkdown(
                doc([
                    {
                        type: 'paragraph',
                        content: [
                            { type: 'text', text: 'bold', marks: [{ type: 'bold' }] },
                            { type: 'text', text: ' ' },
                            { type: 'text', text: 'italic', marks: [{ type: 'italic' }] },
                            { type: 'text', text: ' ' },
                            { type: 'text', text: 'code', marks: [{ type: 'code' }] },
                        ],
                    },
                ]),
            ),
        ).toBe('**bold** *italic* `code`\n');
    });

    it('serializes links', () => {
        expect(
            documentToMarkdown(
                doc([
                    {
                        type: 'paragraph',
                        content: [
                            {
                                type: 'text',
                                text: 'LIFESYNC',
                                marks: [{ type: 'link', attrs: { href: 'https://lifesync.local' } }],
                            },
                        ],
                    },
                ]),
            ),
        ).toBe('[LIFESYNC](https://lifesync.local)\n');
    });

    it('serializes bullet lists with nesting', () => {
        expect(
            documentToMarkdown(
                doc([
                    {
                        type: 'bulletList',
                        content: [
                            {
                                type: 'listItem',
                                content: [
                                    { type: 'paragraph', content: [{ type: 'text', text: 'A' }] },
                                ],
                            },
                            {
                                type: 'listItem',
                                content: [
                                    { type: 'paragraph', content: [{ type: 'text', text: 'B' }] },
                                    {
                                        type: 'bulletList',
                                        content: [
                                            {
                                                type: 'listItem',
                                                content: [
                                                    {
                                                        type: 'paragraph',
                                                        content: [{ type: 'text', text: 'B1' }],
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                ]),
            ),
        ).toBe('- A\n- B\n  - B1\n');
    });

    it('serializes ordered lists with start index', () => {
        expect(
            documentToMarkdown(
                doc([
                    {
                        type: 'orderedList',
                        attrs: { start: 3 },
                        content: [
                            {
                                type: 'listItem',
                                content: [
                                    { type: 'paragraph', content: [{ type: 'text', text: 'X' }] },
                                ],
                            },
                        ],
                    },
                ]),
            ),
        ).toBe('3. X\n');
    });

    it('serializes task lists with checked state', () => {
        expect(
            documentToMarkdown(
                doc([
                    {
                        type: 'taskList',
                        content: [
                            {
                                type: 'taskItem',
                                attrs: { checked: true },
                                content: [
                                    { type: 'paragraph', content: [{ type: 'text', text: 'Done' }] },
                                ],
                            },
                            {
                                type: 'taskItem',
                                attrs: { checked: false },
                                content: [
                                    { type: 'paragraph', content: [{ type: 'text', text: 'Todo' }] },
                                ],
                            },
                        ],
                    },
                ]),
            ),
        ).toBe('- [x] Done\n- [ ] Todo\n');
    });

    it('serializes blockquotes', () => {
        expect(
            documentToMarkdown(
                doc([
                    {
                        type: 'blockquote',
                        content: [
                            { type: 'paragraph', content: [{ type: 'text', text: 'Quote' }] },
                        ],
                    },
                ]),
            ),
        ).toBe('> Quote\n');
    });

    it('serializes code blocks with language', () => {
        expect(
            documentToMarkdown(
                doc([
                    {
                        type: 'codeBlock',
                        attrs: { language: 'php' },
                        content: [{ type: 'text', text: '<?php echo 1;' }],
                    },
                ]),
            ),
        ).toBe('```php\n<?php echo 1;\n```\n');
    });

    it('serializes horizontal rules and hard breaks', () => {
        expect(
            documentToMarkdown(
                doc([
                    { type: 'horizontalRule' },
                    {
                        type: 'paragraph',
                        content: [
                            { type: 'text', text: 'Line' },
                            { type: 'hardBreak' },
                            { type: 'text', text: 'Broken' },
                        ],
                    },
                ]),
            ),
        ).toBe('---\n\nLine  \nBroken\n');
    });
});

describe('documentToPlainText', () => {
    it('joins text blocks', () => {
        expect(
            documentToPlainText(
                doc([
                    { type: 'paragraph', content: [{ type: 'text', text: 'Alpha' }] },
                    { type: 'paragraph', content: [{ type: 'text', text: 'Beta' }] },
                ]),
            ),
        ).toBe('Alpha\nBeta\n');
    });

    it('ignores markdown markers', () => {
        expect(
            documentToPlainText(
                doc([
                    { type: 'heading', attrs: { level: 1 }, content: [{ type: 'text', text: 'Title' }] },
                    {
                        type: 'bulletList',
                        content: [
                            {
                                type: 'listItem',
                                content: [
                                    { type: 'paragraph', content: [{ type: 'text', text: 'Item' }] },
                                ],
                            },
                        ],
                    },
                ]),
            ),
        ).toBe('Title\nItem\n');
    });

    it('produces trailing newline and trims blank blocks', () => {
        expect(documentToPlainText(doc(undefined))).toBe('\n');
    });
});