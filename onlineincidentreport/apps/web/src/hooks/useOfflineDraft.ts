import { useState, useEffect, useCallback, useRef } from 'react';

export function useOfflineDraft<T>(key: string, initialValue: T | (() => T)) {
  const currentKeyRef = useRef(key);
  const skipSaveRef = useRef(false);
  const debounceTimerRef = useRef<any>(null);

  const getInitial = useCallback((): T => {
    return typeof initialValue === 'function' ? (initialValue as () => T)() : initialValue;
  }, [initialValue]);

  const [data, setData] = useState<T>(() => {
    try {
      const saved = localStorage.getItem(key);
      if (saved) {
        return JSON.parse(saved);
      }
    } catch (e) {
      console.warn('Failed to load draft from localStorage', e);
    }
    return getInitial();
  });

  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [isOnline, setIsOnline] = useState<boolean>(navigator.onLine);
  const isInitialKeyLoad = useRef(true);

  useEffect(() => {
    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Sync state if key changes
  useEffect(() => {
    if (currentKeyRef.current !== key) {
      currentKeyRef.current = key;
      isInitialKeyLoad.current = true;
      skipSaveRef.current = false;
      try {
        const saved = localStorage.getItem(key);
        if (saved) {
          setData(JSON.parse(saved));
          return;
        }
      } catch (e) {
        console.warn('Failed to load draft from localStorage on key change', e);
      }
      setData(getInitial());
    }
  }, [key, getInitial]);

  // Save to localStorage with debounce
  useEffect(() => {
    if (isInitialKeyLoad.current) {
      isInitialKeyLoad.current = false;
      return;
    }

    if (skipSaveRef.current) {
      skipSaveRef.current = false;
      return;
    }

    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    debounceTimerRef.current = setTimeout(() => {
      try {
        localStorage.setItem(key, JSON.stringify(data));
        setLastSaved(new Date());
      } catch (e) {
        console.warn('Failed to save draft to localStorage', e);
      }
    }, 400);

    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [key, data]);

  const clearDraft = useCallback(() => {
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
      debounceTimerRef.current = null;
    }
    skipSaveRef.current = true;
    try {
      localStorage.removeItem(key);
      // Clean up any remaining draft keys from browser
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const k = localStorage.key(i);
        if (k && k.startsWith('uss_draft_')) {
          localStorage.removeItem(k);
        }
      }
    } catch (e) {
      console.warn('Failed to clear draft from localStorage', e);
    }
    const fresh = getInitial();
    setData(fresh);
    setLastSaved(null);
  }, [key, getInitial]);

  return {
    data,
    setData,
    lastSaved,
    isOnline,
    clearDraft,
  };
}
