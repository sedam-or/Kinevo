import { z } from "zod";

export const setupAccountSchema = z.object({
  email: z.string().trim().email(),
  password: z.string().min(1).max(128),
  display_name: z.string().max(80).optional(),
  timezone: z.string().min(1),
});

export type SetupAccountValues = z.infer<typeof setupAccountSchema>;
