import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Badge } from "./Badge";
import { Banner } from "./Banner";
import { Card, Section } from "./Card";
import { ConflictCard } from "./ConflictCard";
import { EmptyState } from "./EmptyState";
import { ProgressBar } from "./ProgressBar";

describe("feedback and layout primitives", () => {
  it("renders text-backed status, persistent banner, semantic cards and progress", () => {
    render(
      <>
        <Badge icon={<span aria-hidden="true">●</span>} variant="success">
          Saved
        </Badge>
        <Banner variant="warning" title="Limited access">
          Some items are read-only.
        </Banner>
        <Card title="Overview">
          <p>Current overview</p>
        </Card>
        <Section title="Details">
          <p>Current details</p>
        </Section>
        <EmptyState
          headline="Nothing here"
          body="Create an item to get started."
          action={<button type="button">Create item</button>}
        />
        <ProgressBar label="Upload progress" value={40} maxValue={100} />
        <ConflictCard
          yourChange="Your change"
          currentVersion="Current version"
          onKeepMine={() => undefined}
          onUseCurrent={() => undefined}
        />
      </>,
    );

    expect(screen.getByText("Saved")).toBeInTheDocument();
    expect(screen.getByRole("status")).toHaveTextContent("Limited access");
    expect(screen.getByRole("region", { name: "Overview" })).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Details" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Nothing here" })).toBeInTheDocument();
    expect(screen.getByRole("progressbar", { name: "Upload progress" })).toHaveAttribute(
      "aria-valuenow",
      "40",
    );
    expect(screen.getByRole("heading", { name: "Your change" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Keep my change" })).toBeInTheDocument();
  });

  it("does not expose a dismissal action for a critical banner", () => {
    render(
      <Banner
        variant="danger"
        title="Service unavailable"
        isCritical
        isDismissable
        onDismiss={() => undefined}
      >
        Contact the administrator.
      </Banner>,
    );
    expect(screen.getByRole("alert")).toHaveTextContent("Service unavailable");
    expect(screen.queryByRole("button", { name: "Dismiss banner" })).not.toBeInTheDocument();
  });
});
