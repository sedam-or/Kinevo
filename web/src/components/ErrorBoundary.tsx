import { Component, type ErrorInfo, type ReactNode } from "react";
import { ErrorCard } from "./ErrorCard";
import type { AppError } from "./form/use-form-errors";

type ErrorBoundaryProps = { children: ReactNode; requestId?: string; onReload?: () => void };
type ErrorBoundaryState = { error: AppError | null };

function createLocalError(requestId: string): AppError {
  return {
    code: "internal.error",
    detail: "",
    request_id: requestId,
    status: 500,
    title: "",
    type: "about:blank",
  };
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  override state: ErrorBoundaryState = { error: null };

  static getDerivedStateFromError(): ErrorBoundaryState {
    return { error: createLocalError(crypto.randomUUID()) };
  }

  override componentDidCatch(_error: Error, _info: ErrorInfo) {
    // Error content and stack traces are deliberately not exposed or logged here.
  }

  private reloadSection = () => {
    this.props.onReload?.();
    this.setState({ error: null });
  };

  override render() {
    if (this.state.error) {
      const error = this.props.requestId
        ? { ...this.state.error, request_id: this.props.requestId }
        : this.state.error;
      return <ErrorCard error={error} onRetry={this.reloadSection} />;
    }

    return this.props.children;
  }
}
