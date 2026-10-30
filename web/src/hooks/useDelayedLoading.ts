import { useEffect, useState } from "react";

export function useDelayedLoading(isLoading: boolean, delay = 400) {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    if (!isLoading) {
      setIsVisible(false);
      return;
    }

    const timeout = setTimeout(() => setIsVisible(true), delay);
    return () => clearTimeout(timeout);
  }, [delay, isLoading]);

  return isLoading && isVisible;
}
