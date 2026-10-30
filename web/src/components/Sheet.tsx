import { Dialog, type DialogProps } from "./Dialog";

export type SheetProps = DialogProps;

export function Sheet(props: SheetProps) {
  return (
    <Dialog
      {...props}
      panelClassName="ml-auto h-full max-h-none max-w-md rounded-l-lg rounded-r-none data-[entering]:translate-x-2 data-[exiting]:translate-x-2 motion-safe:transition-transform motion-safe:duration-fast"
    />
  );
}
