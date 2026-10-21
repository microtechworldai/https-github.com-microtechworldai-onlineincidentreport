import React, { useRef, useEffect, useState, useCallback } from 'react';
import {
  Bold,
  Italic,
  Underline,
  List,
  ListOrdered,
  RemoveFormatting,
  Undo2,
  Redo2,
  Palette,
  Minus,
  Plus,
  AlignLeft,
  ChevronDown,
} from 'lucide-react';
import { sanitizeRichText, htmlToPlainText } from '@uss/shared';

interface RichTextEditorProps {
  value: string;
  onChange: (html: string, plainText: string, lineCount: number) => void;
  placeholder?: string;
  disabled?: boolean;
}

const COMMON_COLORS = [
  { name: 'Black', hex: '#000000' },
  { name: 'Navy', hex: '#0F2042' },
  { name: 'Blue', hex: '#2563EB' },
  { name: 'Red', hex: '#DC2626' },
  { name: 'Amber', hex: '#D97706' },
  { name: 'Green', hex: '#16A34A' },
  { name: 'Slate', hex: '#475569' },
];

const FONT_FAMILIES = [
  { label: 'Sans-Serif', value: 'system-ui, -apple-system, sans-serif' },
  { label: 'Arial / Helvetica', value: 'Arial, Helvetica, sans-serif' },
  { label: 'Times New Roman', value: '"Times New Roman", Times, serif' },
  { label: 'Georgia', value: 'Georgia, serif' },
  { label: 'Courier New', value: '"Courier New", Courier, monospace' },
  { label: 'Trebuchet MS', value: '"Trebuchet MS", sans-serif' },
  { label: 'Verdana', value: 'Verdana, sans-serif' },
];

const FONT_SIZES = ['10pt', '11pt', '12pt', '14pt', '16pt', '18pt', '20pt', '24pt'];
const LINE_SPACINGS = [
  { label: '1.0', value: '1.0' },
  { label: '1.15', value: '1.15' },
  { label: '1.5', value: '1.5' },
  { label: '2.0', value: '2.0' },
];

export const RichTextEditor: React.FC<RichTextEditorProps> = ({
  value,
  onChange,
  placeholder = 'Describe the incident in full chronological detail (What, Where, When, Who, Why, How)...',
  disabled = false,
}) => {
  const editorRef = useRef<HTMLDivElement>(null);
  const [currentFontFamily, setCurrentFontFamily] = useState<string>(FONT_FAMILIES[0].value);
  const [currentFontSize, setCurrentFontSize] = useState<string>('11pt');
  const [currentColor, setCurrentColor] = useState<string>('#000000');
  const [currentLineSpacing, setCurrentLineSpacing] = useState<string>('1.15');
  const [showColorPicker, setShowColorPicker] = useState<boolean>(false);
  const [lineCount, setLineCount] = useState<number>(0);
  const [wordCount, setWordCount] = useState<number>(0);

  const isUpdatingRef = useRef<boolean>(false);

  // Calculate lines & words
  const calculateMetrics = useCallback((text: string) => {
    const lines = text ? text.split(/\r?\n/).length : 0;
    const words = text ? text.trim().split(/\s+/).filter(Boolean).length : 0;
    setLineCount(lines);
    setWordCount(words);
    return { lines, words };
  }, []);

  // Sync value from props only if changed externally
  useEffect(() => {
    if (editorRef.current && !isUpdatingRef.current) {
      if (editorRef.current.innerHTML !== value) {
        editorRef.current.innerHTML = value || '';
        const plain = htmlToPlainText(value || '');
        calculateMetrics(plain);
      }
    }
  }, [value, calculateMetrics]);

  const handleInput = useCallback(() => {
    if (!editorRef.current) return;
    isUpdatingRef.current = true;
    const rawHtml = editorRef.current.innerHTML;
    const sanitized = sanitizeRichText(rawHtml);
    calculateMetrics(sanitized.text);
    onChange(sanitized.html, sanitized.text, sanitized.lineCount);
    setTimeout(() => {
      isUpdatingRef.current = false;
    }, 50);
  }, [onChange, calculateMetrics]);

  // Clean pasted content from Word or external web pages
  const handlePaste = useCallback((e: React.ClipboardEvent<HTMLDivElement>) => {
    e.preventDefault();
    const pastedHtml = e.clipboardData.getData('text/html');
    const pastedText = e.clipboardData.getData('text/plain');

    if (pastedHtml) {
      const sanitized = sanitizeRichText(pastedHtml);
      document.execCommand('insertHTML', false, sanitized.html);
    } else if (pastedText) {
      document.execCommand('insertText', false, pastedText);
    }
    handleInput();
  }, [handleInput]);

  // Command Helper
  const applyCommand = (command: string, value: string | undefined = undefined) => {
    if (disabled || !editorRef.current) return;
    editorRef.current.focus();
    document.execCommand(command, false, value);
    handleInput();
  };

  // Font Family Helper
  const changeFontFamily = (family: string) => {
    setCurrentFontFamily(family);
    const selection = window.getSelection();
    if (selection && selection.rangeCount > 0 && !selection.isCollapsed) {
      const span = document.createElement('span');
      span.style.fontFamily = family;
      const range = selection.getRangeAt(0);
      try {
        range.surroundContents(span);
      } catch (_) {
        document.execCommand('fontName', false, family);
      }
    } else if (editorRef.current) {
      editorRef.current.style.fontFamily = family;
    }
    handleInput();
  };

  // Font Size helpers
  const changeFontSize = (sizeStr: string) => {
    setCurrentFontSize(sizeStr);
    const selection = window.getSelection();
    if (selection && selection.rangeCount > 0 && !selection.isCollapsed) {
      const span = document.createElement('span');
      span.style.fontSize = sizeStr;
      const range = selection.getRangeAt(0);
      try {
        range.surroundContents(span);
      } catch (_) {
        document.execCommand('fontSize', false, '3');
      }
    } else if (editorRef.current) {
      editorRef.current.style.fontSize = sizeStr;
    }
    handleInput();
  };

  const stepFontSize = (delta: number) => {
    const currentIndex = FONT_SIZES.indexOf(currentFontSize);
    const newIndex = Math.max(0, Math.min(FONT_SIZES.length - 1, (currentIndex !== -1 ? currentIndex : 1) + delta));
    changeFontSize(FONT_SIZES[newIndex]);
  };

  // Line Spacing
  const changeLineSpacing = (spacing: string) => {
    setCurrentLineSpacing(spacing);
    if (editorRef.current) {
      editorRef.current.style.lineHeight = spacing;
    }
    handleInput();
  };

  // Text Color
  const changeTextColor = (hex: string) => {
    setCurrentColor(hex);
    setShowColorPicker(false);
    applyCommand('foreColor', hex);
  };

  return (
    <div className="border border-slate-300 rounded-2xl overflow-hidden bg-white shadow-xs focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
      {/* Touch-Friendly Toolbar (Sticky above keyboard on mobile) */}
      <div className="sticky top-0 z-10 bg-slate-50/95 backdrop-blur-xs border-b border-slate-200 px-2 py-1.5 flex flex-wrap items-center gap-1 sm:gap-1.5 select-none overflow-x-auto">
        {/* Undo / Redo */}
        <div className="flex items-center border-r border-slate-200 pr-1 mr-0.5">
          <button
            type="button"
            onClick={() => applyCommand('undo')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-700 rounded-lg transition-colors touch-target"
            title="Undo (Ctrl+Z)"
          >
            <Undo2 className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => applyCommand('redo')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-700 rounded-lg transition-colors touch-target"
            title="Redo (Ctrl+Y)"
          >
            <Redo2 className="w-4 h-4" />
          </button>
        </div>

        {/* Font Family Selector */}
        <div className="flex items-center border-r border-slate-200 pr-1.5 mr-0.5">
          <select
            value={currentFontFamily}
            onChange={(e) => changeFontFamily(e.target.value)}
            className="h-8 text-xs font-bold bg-white border border-slate-200 rounded-lg px-2 text-slate-800 focus:outline-none focus:border-blue-500 cursor-pointer max-w-[130px] sm:max-w-none truncate"
            title="Custom Font Family"
          >
            {FONT_FAMILIES.map((f) => (
              <option key={f.value} value={f.value}>
                {f.label}
              </option>
            ))}
          </select>
        </div>

        {/* Font Size: A- / A+ / Dropdown */}
        <div className="flex items-center gap-1 border-r border-slate-200 pr-1.5 mr-0.5">
          <button
            type="button"
            onClick={() => stepFontSize(-1)}
            className="px-2 py-1.5 hover:bg-slate-200/70 active:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition-colors flex items-center gap-0.5"
            title="Decrease font size"
          >
            <Minus className="w-3 h-3" />
            <span>A</span>
          </button>

          <select
            value={currentFontSize}
            onChange={(e) => changeFontSize(e.target.value)}
            className="h-8 text-xs font-bold bg-white border border-slate-200 rounded-lg px-1.5 py-0.5 text-slate-700 focus:outline-none focus:border-blue-500 cursor-pointer"
            title="Font Size"
          >
            {FONT_SIZES.map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </select>

          <button
            type="button"
            onClick={() => stepFontSize(1)}
            className="px-2 py-1.5 hover:bg-slate-200/70 active:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition-colors flex items-center gap-0.5"
            title="Increase font size"
          >
            <Plus className="w-3 h-3" />
            <span>A</span>
          </button>
        </div>

        {/* Formatting: Bold, Italic, Underline */}
        <div className="flex items-center gap-0.5 border-r border-slate-200 pr-1 mr-0.5">
          <button
            type="button"
            onClick={() => applyCommand('bold')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-800 rounded-lg transition-colors font-bold"
            title="Bold (Ctrl+B)"
          >
            <Bold className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => applyCommand('italic')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-800 rounded-lg transition-colors"
            title="Italic (Ctrl+I)"
          >
            <Italic className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => applyCommand('underline')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-800 rounded-lg transition-colors"
            title="Underline (Ctrl+U)"
          >
            <Underline className="w-4 h-4" />
          </button>
        </div>

        {/* Text Color Picker */}
        <div className="relative flex items-center border-r border-slate-200 pr-1.5 mr-0.5">
          <button
            type="button"
            onClick={() => setShowColorPicker(!showColorPicker)}
            className="flex items-center gap-1.5 px-2 py-1.5 hover:bg-slate-200/70 rounded-lg text-slate-700 transition-colors"
            title="Text Color"
          >
            <Palette className="w-4 h-4" />
            <span
              className="w-3.5 h-3.5 rounded-full border border-slate-300 shadow-2xs"
              style={{ backgroundColor: currentColor }}
            />
            <ChevronDown className="w-3 h-3 text-slate-400" />
          </button>

          {showColorPicker && (
            <div className="absolute top-full left-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg p-2.5 z-30 min-w-[160px] space-y-2">
              <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                Text Colors
              </span>
              <div className="grid grid-cols-4 gap-1.5">
                {COMMON_COLORS.map((c) => (
                  <button
                    key={c.hex}
                    type="button"
                    onClick={() => changeTextColor(c.hex)}
                    className="w-7 h-7 rounded-lg border border-slate-200 flex items-center justify-center hover:scale-110 active:scale-95 transition-all"
                    style={{ backgroundColor: c.hex }}
                    title={c.name}
                  />
                ))}
              </div>
              <div className="pt-1.5 border-t border-slate-100 flex items-center gap-2">
                <span className="text-[11px] text-slate-600 font-medium">Custom:</span>
                <input
                  type="color"
                  value={currentColor}
                  onChange={(e) => changeTextColor(e.target.value)}
                  className="w-6 h-6 rounded cursor-pointer border-0 p-0"
                />
              </div>
            </div>
          )}
        </div>

        {/* Line Spacing */}
        <div className="flex items-center gap-1 border-r border-slate-200 pr-1.5 mr-0.5">
          <span className="text-[10px] font-bold text-slate-400 uppercase hidden sm:inline">Spacing:</span>
          <select
            value={currentLineSpacing}
            onChange={(e) => changeLineSpacing(e.target.value)}
            className="h-8 text-xs font-semibold bg-white border border-slate-200 rounded-lg px-1.5 text-slate-700 focus:outline-none focus:border-blue-500 cursor-pointer"
            title="Line Spacing (1.0, 1.15, 1.5, 2.0)"
          >
            {LINE_SPACINGS.map((sp) => (
              <option key={sp.value} value={sp.value}>
                {sp.label}x
              </option>
            ))}
          </select>
        </div>

        {/* Lists & Clear Formatting */}
        <div className="flex items-center gap-0.5">
          <button
            type="button"
            onClick={() => applyCommand('insertUnorderedList')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-700 rounded-lg transition-colors"
            title="Bullet List"
          >
            <List className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => applyCommand('insertOrderedList')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-700 rounded-lg transition-colors"
            title="Numbered List"
          >
            <ListOrdered className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => applyCommand('removeFormat')}
            className="p-2 hover:bg-slate-200/70 active:bg-slate-300 text-slate-500 hover:text-slate-700 rounded-lg transition-colors"
            title="Clear Formatting"
          >
            <RemoveFormatting className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Editor Content Area (Guaranteed >= 15 lines visible height) */}
      <div
        ref={editorRef}
        contentEditable={!disabled}
        onInput={handleInput}
        onPaste={handlePaste}
        data-placeholder={placeholder}
        style={{
          fontFamily: currentFontFamily,
          fontSize: currentFontSize,
          lineHeight: currentLineSpacing,
        }}
        className="p-4 min-h-[310px] max-h-[620px] overflow-y-auto focus:outline-none text-slate-900 empty:before:content-[attr(data-placeholder)] empty:before:text-slate-400 empty:before:pointer-events-none prose prose-slate max-w-none font-normal"
      />

      {/* Live Status Footer (Line counter, Word counter, Reading estimate) */}
      <div className="bg-slate-50 border-t border-slate-200/80 px-4 py-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 font-medium">
        <div className="flex items-center gap-3">
          <span className="flex items-center gap-1 font-bold text-slate-700">
            <span>Lines:</span>
            <span className={lineCount >= 15 ? 'text-emerald-700 font-extrabold' : 'text-slate-900 font-bold'}>
              {lineCount}
            </span>
            <span className="text-[10px] text-slate-400 font-normal">
              {lineCount >= 15 ? '(fits Page 1 & overflows cleanly)' : '(min 15 lines fit Page 1)'}
            </span>
          </span>

          <span className="text-slate-300">|</span>

          <span>{wordCount} words</span>
        </div>

        <div className="text-[11px] text-slate-400">
          Formatted text is rendered identically in Mobile, Dashboard, and PDF
        </div>
      </div>
    </div>
  );
};
