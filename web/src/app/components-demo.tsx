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
import { useT } from "../i18n";

export function ComponentsDemo() {
  const toast = useToast();
  const t = useT();
  return (
    <main className="mx-auto grid max-w-3xl gap-8 p-6 text-ink">
      <h1 className="font-display text-title-1">{t("demo.heading")}</h1>
      <Section title={t("demo.actions")}>
        <div className="flex flex-wrap items-center gap-3">
          <Button>{t("demo.primary_action")}</Button>
          <Button variant="secondary">{t("demo.secondary_action")}</Button>
          <Button variant="quiet">{t("demo.quiet_action")}</Button>
          <Button variant="danger">{t("demo.danger_action")}</Button>
          <IconButton
            aria-label={t("action.close_preview")}
            icon={<span aria-hidden="true">×</span>}
          />
          <Button onPress={() => toast.success("state.synced")}>
            {t("demo.show_success_toast")}
          </Button>
          <Button
            onPress={() =>
              toast.error({ messageKey: "sync.rejected", requestId: "demo-request-1" })
            }
          >
            {t("demo.show_error_toast")}
          </Button>
        </div>
      </Section>
      <Section title={t("demo.fields")}>
        <div className="grid gap-4">
          <TextField
            label={t("demo.email")}
            type="email"
            autoComplete="email"
            description={t("demo.email_description")}
          />
          <TextField label={t("demo.password")} type="password" autoComplete="current-password" />
          <TextArea label={t("demo.notes")} description={t("demo.optional_note")} maxLength={200} />
          <NumberField label={t("demo.sessions")} defaultValue={1} minValue={0} maxValue={10} />
          <Select
            label={t("demo.theme")}
            defaultSelectedKey="light"
            items={[
              { id: "light", label: t("demo.light") },
              { id: "dark", label: t("demo.dark") },
            ]}
          />
          <Switch
            label={t("demo.canvas")}
            onLabel={t("demo.on")}
            offLabel={t("demo.off")}
            defaultSelected
          />
          <Checkbox label={t("demo.remember_device")} />
          <RadioGroup
            label={t("demo.layout")}
            options={[
              { value: "comfortable", label: t("demo.comfortable") },
              { value: "compact", label: t("demo.compact") },
            ]}
          />
        </div>
      </Section>
      <Section title={t("demo.feedback_layout")}>
        <div className="grid gap-4">
          <div className="flex flex-wrap gap-2">
            <Badge icon={<span aria-hidden="true">●</span>} variant="success">
              {t("demo.saved")}
            </Badge>
            <Badge icon={<span aria-hidden="true">●</span>} variant="warning">
              {t("demo.needs_review")}
            </Badge>
          </div>
          <Banner variant="info" title={t("demo.connection_restored")}>
            {t("demo.changes_up_to_date")}
          </Banner>
          <Card title={t("demo.project_summary")}>
            <p>{t("demo.section_ready")}</p>
          </Card>
          <EmptyState
            headline={t("demo.no_scheduled_items")}
            body={t("demo.make_room_for_focus")}
            action={<Button>{t("demo.create_item")}</Button>}
          />
          <ProgressBar label={t("demo.setup_progress")} value={40} />
          <Skeleton isLoading />
          <ConflictCard
            yourChange={t("demo.updated_note")}
            currentVersion={t("demo.earlier_note")}
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
      <Section title={t("demo.navigation_overlays")}>
        <div className="flex flex-wrap gap-3">
          <Dialog
            trigger={<Button variant="secondary">{t("demo.open_dialog")}</Button>}
            title={t("demo.edit_preview")}
            description={t("demo.unsaved_preview")}
          >
            <p>{t("demo.dialog_content")}</p>
          </Dialog>
          <Sheet
            trigger={<Button variant="secondary">{t("demo.open_sheet")}</Button>}
            title={t("demo.preview_details")}
            description={t("demo.side_panel_description")}
          >
            <p>{t("demo.sheet_content")}</p>
          </Sheet>
        </div>
        <Tabs
          label={t("demo.preview_categories")}
          items={[
            {
              id: "overview",
              label: t("demo.overview"),
              content: <p>{t("demo.overview_content")}</p>,
            },
            {
              id: "activity",
              label: t("demo.activity"),
              content: <p>{t("demo.activity_content")}</p>,
            },
          ]}
        />
      </Section>
      <Link href="https://example.test/help" openInNewTab>
        {t("demo.help_guide")}
      </Link>
    </main>
  );
}
