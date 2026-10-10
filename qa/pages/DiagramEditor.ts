import { expect, type Locator, type Page } from '@playwright/test';

/**
 * The visual diagram editor (frontend/src/components/diagram/DiagramDialog.vue), opened
 * from a note editor's "Diagram" button. Built on Vue Flow: shapes are `.vue-flow__node`,
 * connectors `.vue-flow__edge`, and each shape has four connection dots (handles t/r/b/l).
 */
export class DiagramEditor {
  readonly dialog: Locator;
  readonly shapes: Locator;
  readonly connectors: Locator;
  readonly panel: Locator;
  readonly insert: Locator;
  readonly canvas: Locator;
  readonly inlineText: Locator;

  constructor(private readonly page: Page) {
    this.dialog = page.getByRole('dialog', { name: /^(Diagram|Edit diagram)$/ });
    this.shapes = this.dialog.locator('.vue-flow__node');
    this.connectors = this.dialog.locator('.vue-flow__edge');
    this.panel = this.dialog.getByRole('complementary', { name: 'Selected item' });
    this.insert = this.dialog.getByRole('button', { name: /^(Insert|Update) diagram$/ });
    this.canvas = this.dialog.locator('.dg-canvas');
    this.inlineText = this.dialog.getByLabel('Shape text');
  }

  async chooseSet(name: 'Flowchart' | 'UML' | 'Database'): Promise<void> {
    await this.dialog.getByRole('tab', { name }).click();
  }

  shapeButton(name: string): Locator {
    return this.dialog.getByRole('group', { name: /Add (a shape|text)/ }).getByRole('button', { name, exact: true });
  }

  shape(text: string): Locator {
    return this.shapes.filter({ hasText: text }).first();
  }

  /**
   * Adds a shape that's named in place and types its name. Flowchart shapes finish on Enter;
   * text and notes keep Enter for new lines, so those finish by clicking away, as a person would.
   */
  async addNamedShape(button: string, name: string): Promise<void> {
    await this.shapeButton(button).click();
    await expect(this.inlineText).toBeFocused();
    await this.page.keyboard.type(name);
    if (button === 'Text' || button === 'Note') await this.clearSelection();
    else await this.page.keyboard.press('Enter');
    await expect(this.shape(name)).toBeVisible();
  }

  /** Pairs of shapes (by index) whose boxes overlap on the canvas. */
  async overlappingShapes(): Promise<[number, number][]> {
    return this.shapes.evaluateAll((els) => {
      const boxes = els.map((el) => el.getBoundingClientRect());
      const pairs: [number, number][] = [];
      boxes.forEach((a, i) =>
        boxes.slice(i + 1).forEach((b, k) => {
          if (a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom) pairs.push([i, i + 1 + k]);
        }),
      );
      return pairs;
    });
  }

  /** Clicks an empty corner of the canvas, clearing the selection. */
  async clearSelection(): Promise<void> {
    const box = await this.canvas.boundingBox();
    if (!box) throw new Error('Canvas is not on screen');
    await this.page.mouse.click(box.x + 12, box.y + 12);
  }

  /** Drags from one shape's connection dot to another's, creating a connector. */
  async connect(fromText: string, fromSide: 't' | 'r' | 'b' | 'l', toText: string, toSide: 't' | 'r' | 'b' | 'l'): Promise<void> {
    const from = this.shape(fromText);
    await from.hover();
    const start = await from.locator(`.vue-flow__handle[data-handleid="${fromSide}"]`).boundingBox();
    const end = await this.shape(toText).locator(`.vue-flow__handle[data-handleid="${toSide}"]`).boundingBox();
    if (!start || !end) throw new Error('Connection dots are not on screen');
    await this.page.mouse.move(start.x + start.width / 2, start.y + start.height / 2);
    await this.page.mouse.down();
    await this.page.mouse.move(end.x + end.width / 2, end.y + end.height / 2, { steps: 15 });
    await this.page.mouse.up();
  }

  /** Selects the most recently drawn connector by clicking the middle of its line. */
  async selectLastConnector(): Promise<void> {
    const point = await this.dialog.locator('.vue-flow__edge path.dg-edge').last().evaluate((el) => {
      const path = el as SVGPathElement;
      const mid = path.getPointAtLength(path.getTotalLength() / 2);
      const m = path.getScreenCTM()!;
      return { x: mid.x * m.a + m.e, y: mid.y * m.d + m.f };
    });
    await this.page.mouse.click(point.x, point.y);
  }

  /** Side-panel field for the selection, by its label (Name, Attributes, Style, Start label, …). */
  field(label: string | RegExp): Locator {
    return this.panel.getByLabel(label);
  }
}
