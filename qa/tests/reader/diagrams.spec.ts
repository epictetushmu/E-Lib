import type { Page } from '@playwright/test';
import { expect, test } from '../../fixtures';
import { DiagramEditor } from '../../pages/DiagramEditor';
import { ReaderPage } from '../../pages/ReaderPage';

/** The ```diagram JSON block a note editor holds, parsed. */
function diagramIn(note: string): { nodes: Record<string, unknown>[]; edges: Record<string, unknown>[] } {
  const block = note.match(/```diagram\n([\s\S]*?)\n```/);
  if (!block) throw new Error(`No diagram block in: ${note}`);
  return JSON.parse(block[1]);
}

/** Opens the book's notes panel and the diagram editor from its toolbar. */
async function openDiagramEditor(page: Page, bookId: string): Promise<{ view: ReaderPage; editor: DiagramEditor }> {
  const view = new ReaderPage(page);
  const editor = new DiagramEditor(page);
  await view.open(bookId);
  await view.openNotes();
  await view.insertButton(view.notesPanel, 'Diagram').click();
  return { view, editor };
}

test.describe('Reader: drawing diagrams in notes', () => {
  test('a flowchart is built by clicking shapes, each one following and connected to the last', async ({ reader, readerBook }) => {
    const { view, editor } = await openDiagramEditor(reader.page, readerBook.id);
    await expect(editor.dialog).toBeVisible();

    await editor.addNamedShape('Start / end', 'Start');
    await editor.addNamedShape('Step', 'Read input');
    await editor.addNamedShape('Decision', 'Valid?');
    await editor.addNamedShape('Step', 'Process');
    await editor.shape('Valid?').click();
    await editor.addNamedShape('Start / end', 'Stop');

    await expect(editor.shapes).toHaveCount(5);
    await expect(editor.connectors).toHaveCount(4);
    // A decision's branches are labelled for you.
    await expect(editor.dialog.locator('.vue-flow__edge-text')).toHaveText(['Yes', 'No']);

    await editor.insert.click();
    await expect(editor.dialog).toBeHidden();
    const doc = diagramIn(await view.noteOnPageEditor.inputValue());
    expect(doc.nodes.map((n) => n.label)).toEqual(['Start', 'Read input', 'Valid?', 'Process', 'Stop']);
    expect(doc.edges.map((e) => e.label ?? '')).toEqual(['', '', 'Yes', 'No']);

    await view.addNoteButton.click();
    const drawn = view.renderedNote(view.noteItems.first()).locator('svg.diagram-svg');
    await expect(drawn).toBeVisible();
    for (const label of ['Start', 'Read input', 'Valid?', 'Process', 'Stop', 'Yes', 'No']) {
      await expect(drawn).toContainText(label);
    }
  });

  test('putting the cursor in a diagram and pressing Diagram reopens it for editing', async ({ reader, readerBook }) => {
    const { view, editor } = await openDiagramEditor(reader.page, readerBook.id);
    await editor.addNamedShape('Step', 'Only step');
    await editor.insert.click();

    await view.noteOnPageEditor.evaluate((el: HTMLTextAreaElement) => el.setSelectionRange(5, 5));
    await view.insertButton(view.notesPanel, 'Diagram').click();

    await expect(editor.dialog).toHaveAccessibleName('Edit diagram');
    await expect(editor.shapes).toHaveCount(1);
    await editor.shape('Only step').click();
    await editor.field('Text').fill('Renamed step');
    await editor.insert.click();

    const doc = diagramIn(await view.noteOnPageEditor.inputValue());
    expect(doc.nodes.map((n) => n.label)).toEqual(['Renamed step']);
    // Replaced in place, not added as a second diagram.
    expect((await view.noteOnPageEditor.inputValue()).match(/```diagram/g)).toHaveLength(1);
  });

  test('UML classes are filled in from the side panel and linked by inheritance', async ({ reader, readerBook }) => {
    const { view, editor } = await openDiagramEditor(reader.page, readerBook.id);
    await editor.chooseSet('UML');

    await editor.shapeButton('Class').click();
    await expect(editor.field('Name')).toBeFocused();
    await reader.page.keyboard.type('Person');
    await editor.field(/^Attributes/).fill('- name: string');
    await editor.field(/^Methods/).fill('+ greet(): void');

    await editor.shapeButton('Class').click();
    await reader.page.keyboard.type('Student');
    await editor.field(/^Attributes/).fill('- studentId: int');

    await expect(editor.shape('Person')).toContainText('- name: string');
    await expect(editor.shape('Person')).toContainText('+ greet(): void');

    await editor.connect('Student', 'l', 'Person', 'r');
    await expect(editor.connectors).toHaveCount(1);
    await editor.selectLastConnector();
    // Between two classes a connector starts as an association; make it inheritance.
    await expect(editor.field('Style')).toHaveValue('association');
    await editor.field('Style').selectOption('inherit');

    await editor.insert.click();
    const doc = diagramIn(await view.noteOnPageEditor.inputValue());
    expect(doc.nodes).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ kind: 'class', label: 'Person', attributes: ['- name: string'], methods: ['+ greet(): void'] }),
        expect.objectContaining({ kind: 'class', label: 'Student', attributes: ['- studentId: int'] }),
      ]),
    );
    expect(doc.edges).toEqual([expect.objectContaining({ kind: 'inherit' })]);
  });

  test('database tables get columns and keys, and are joined one-to-many by default', async ({ reader, readerBook }) => {
    const { view, editor } = await openDiagramEditor(reader.page, readerBook.id);
    await editor.chooseSet('Database');

    await editor.shapeButton('Table').click();
    await reader.page.keyboard.type('users');
    await editor.panel.getByRole('button', { name: 'Add column' }).click();
    await reader.page.keyboard.type('email');
    await reader.page.keyboard.press('Tab');
    await reader.page.keyboard.type('text');

    await editor.shapeButton('Table').click();
    await reader.page.keyboard.type('loans');
    await editor.panel.getByRole('button', { name: 'Add column' }).click();
    await reader.page.keyboard.type('user_id');
    await reader.page.keyboard.press('Tab');
    await reader.page.keyboard.type('int');
    await editor.panel.getByRole('checkbox', { name: 'Foreign key' }).last().check();

    await expect(editor.shape('users')).toContainText('PK');
    await expect(editor.shape('loans')).toContainText('FK');

    await editor.connect('users', 'r', 'loans', 'l');
    await editor.selectLastConnector();
    await expect(editor.field('Style')).toHaveValue('one-many');
    await editor.field('Start label').fill('1');
    await editor.field('End label').fill('*');

    await editor.insert.click();
    const doc = diagramIn(await view.noteOnPageEditor.inputValue());
    expect(doc.nodes).toEqual([
      expect.objectContaining({
        kind: 'table',
        label: 'users',
        columns: [expect.objectContaining({ name: 'id', type: 'int', pk: true }), expect.objectContaining({ name: 'email', type: 'text' })],
      }),
      expect.objectContaining({
        kind: 'table',
        label: 'loans',
        columns: [expect.objectContaining({ name: 'id', type: 'int', pk: true }), expect.objectContaining({ name: 'user_id', type: 'int', fk: true })],
      }),
    ]);
    expect(doc.edges).toEqual([expect.objectContaining({ kind: 'one-many', sourceLabel: '1', targetLabel: '*' })]);
  });

  test('new shapes never land on top of existing ones', async ({ reader, readerBook }) => {
    const { editor } = await openDiagramEditor(reader.page, readerBook.id);

    await editor.addNamedShape('Step', 'First');
    await editor.clearSelection();
    await editor.addNamedShape('Step', 'Second');
    await editor.clearSelection();
    await editor.addNamedShape('Note', 'Third');

    await expect(editor.shapes).toHaveCount(3);
    expect(await editor.overlappingShapes()).toEqual([]);
  });
});
