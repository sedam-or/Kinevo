import { Badge } from "../components/Badge";
import { Banner } from "../components/Banner";
import { Button } from "../components/Button";
import { Card, Section } from "../components/Card";
import { Checkbox } from "../components/Checkbox";
import { ConflictCard } from "../components/ConflictCard";
import { Dialog } from "../components/Dialog";
import { EmptyState } from "../components/EmptyState";
import { ErrorCard } from "../components/ErrorCard";
import { IconButton } from "../components/IconButton";
import { Link } from "../components/Link";
import { NumberField } from "../components/NumberField";
import { ProgressBar } from "../components/ProgressBar";
import { RadioGroup } from "../components/RadioGroup";
import { Select } from "../components/Select";
import { Sheet } from "../components/Sheet";
import { Skeleton } from "../components/Skeleton";
import { Switch } from "../components/Switch";
import { Tabs } from "../components/Tabs";
import { TextArea } from "../components/TextArea";
import { TextField } from "../components/TextField";
import { useToast } from "../components/Toast";

export function ComponentsDemo() {
  const toast = useToast();
  return (
    <main className="mx-auto grid max-w-3xl gap-8 p-6 text-ink">
      <h1 className="font-display text-title-1">Component preview</h1>
      <Section title="Actions">
        <div className="flex flex-wrap items-center gap-3">
          <Button>Primary action</Button>
          <Button variant="secondary">Secondary action</Button>
          <Button variant="quiet">Quiet action</Button>
          <Button variant="danger">Danger action</Button>
          <IconButton aria-label="Close preview" icon={<span>×</span>} />
          <Button onPress={() => toast.success("Saved successfully.")}>Show success toast</Button>
          <Button
            onPress={() =>
              toast.error({ messageKey: "Could not save changes.", requestId: "demo-request-1" })
            }
          >
            Show error toast
          </Button>
        </div>
      </Section>
      <Section title="Fields">
        <div className="grid gap-4">
          <TextField
            label="Email address"
            type="email"
            autoComplete="email"
            description="Used for account access"
          />
          <TextField label="Password" type="password" autoComplete="current-password" />
          <TextArea label="Notes" description="Optional note" maxLength={200} />
          <NumberField label="Sessions" defaultValue={1} minValue={0} maxValue={10} />
          <Select
            label="Theme"
            defaultSelectedKey="light"
            items={[
              { id: "light", label: "Light" },
              { id: "dark", label: "Dark" },
            ]}
          />
          <Switch label="Canvas" onLabel="On" offLabel="Off" defaultSelected />
          <Checkbox label="Remember this device" />
          <RadioGroup
            label="Layout"
            options={[
              { value: "comfortable", label: "Comfortable" },
              { value: "compact", label: "Compact" },
            ]}
          />
        </div>
      </Section>
      <Section title="Feedback and layout">
        <div className="grid gap-4">
          <div className="flex flex-wrap gap-2">
            <Badge icon={<span aria-hidden="true">●</span>} variant="success">
              Saved
            </Badge>
            <Badge icon={<span aria-hidden="true">●</span>} variant="warning">
              Needs review
            </Badge>
          </div>
          <Banner variant="info" title="Connection restored">
            Your changes are up to date.
          </Banner>
          <Card title="Project summary">
            <p>One section is ready to review.</p>
          </Card>
          <EmptyState
            headline="No scheduled items"
            body="Add an item to make room for focused work."
            action={<Button>Create item</Button>}
          />
          <ProgressBar label="Setup progress" value={40} />
          <Skeleton isLoading />
          <ConflictCard
            yourChange="Updated note"
            currentVersion="Earlier note"
            onKeepMine={() => undefined}
            onUseCurrent={() => undefined}
          />
          <ErrorCard
            error={{
              code: "internal.error",
              detail: "",
              request_id: "demo-request-2",
              status: 500,
              title: "",
              type: "about:blank",
            }}
            onRetry={() => undefined}
          />
        </div>
      </Section>
      <Section title="Navigation overlays">
        <div className="flex flex-wrap gap-3">
          <Dialog
            trigger={<Button variant="secondary">Open dialog</Button>}
            title="Edit preview"
            description="Changes in this preview are not saved."
          >
            <p>Dialog content</p>
          </Dialog>
          <Sheet
            trigger={<Button variant="secondary">Open sheet</Button>}
            title="Preview details"
            description="A side panel for secondary content."
          >
            <p>Sheet content</p>
          </Sheet>
        </div>
        <Tabs
          label="Preview categories"
          items={[
            { id: "overview", label: "Overview", content: <p>Overview content</p> },
            { id: "activity", label: "Activity", content: <p>Activity content</p> },
          ]}
        />
      </Section>
      <Link href="https://example.test/help" openInNewTab>
        Read the help guide
      </Link>
    </main>
  );
}
