import type { ReactNode } from "react";
import { Tabs as AriaTabs, Tab, TabList, TabPanel } from "react-aria-components";

export type TabItem = { id: string; label: string; content: ReactNode };

export function Tabs({
  label,
  items,
  defaultSelectedKey,
}: {
  label: string;
  items: TabItem[];
  defaultSelectedKey?: string | number | undefined;
}) {
  return (
    <AriaTabs
      aria-label={label}
      {...(defaultSelectedKey !== undefined ? { defaultSelectedKey } : {})}
      className="grid gap-3 text-ink"
    >
      <TabList className="flex gap-1 border-b border-rule" aria-label={label}>
        {items.map((item) => (
          <Tab
            key={item.id}
            id={item.id}
            className="min-h-11 cursor-pointer border-b-2 border-transparent px-3 py-2 text-ink-muted outline-none hover:text-ink focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring data-[selected]:border-accent data-[selected]:font-semibold data-[selected]:text-ink"
          >
            {item.label}
          </Tab>
        ))}
      </TabList>
      {items.map((item) => (
        <TabPanel
          key={item.id}
          id={item.id}
          className="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring"
        >
          {item.content}
        </TabPanel>
      ))}
    </AriaTabs>
  );
}
