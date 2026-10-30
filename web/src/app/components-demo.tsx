import { Button } from "../components/Button";
import { Checkbox } from "../components/Checkbox";
import { IconButton } from "../components/IconButton";
import { Link } from "../components/Link";
import { NumberField } from "../components/NumberField";
import { RadioGroup } from "../components/RadioGroup";
import { Select } from "../components/Select";
import { Switch } from "../components/Switch";
import { TextArea } from "../components/TextArea";
import { TextField } from "../components/TextField";

export function ComponentsDemo() {
  return (
    <main className="mx-auto grid max-w-2xl gap-8 p-6 text-ink">
      <h1 className="font-display text-title-1">Component preview</h1>
      <section aria-labelledby="buttons-heading" className="grid gap-3">
        <h2 id="buttons-heading" className="text-title-3">
          Buttons
        </h2>
        <div className="flex flex-wrap items-center gap-3">
          <Button>Primary action</Button>
          <Button variant="secondary">Secondary action</Button>
          <Button variant="quiet">Quiet action</Button>
          <Button variant="danger">Danger action</Button>
          <IconButton aria-label="Close preview" icon={<span>×</span>} />
        </div>
      </section>
      <section aria-labelledby="fields-heading" className="grid gap-4">
        <h2 id="fields-heading" className="text-title-3">
          Fields
        </h2>
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
          defaultValue="comfortable"
          options={[
            { value: "comfortable", label: "Comfortable" },
            { value: "compact", label: "Compact" },
          ]}
        />
      </section>
      <Link href="https://example.test/help" openInNewTab>
        Read the help guide
      </Link>
    </main>
  );
}
