<?php
/**
 * Universal Theme Picker Component
 * Includes:
 * 1. Reference Themes (Student Result, OripioFin, Inventory Slate, Nexora CRM, EduSphere)
 * 2. Classic Themes (Black Dark, Clean Light, Navy Blue, Royal Blue, Deep Green, Purple, Red, Orange, Yellow, Soft Pink)
 * 3. System Match
 * High Contrast WCAG 2.2 AA Conforming Selector
 */
?>
<div id="themePickerModal" class="theme-modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(10, 15, 29, 0.72); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 16px; box-sizing: border-box;" onclick="if(event.target===this)closeThemePickerModal()">
    <div style="background: var(--card-bg, #FFFFFF); color: var(--text-main, #0F172A); border: 1px solid var(--card-border, #E2E8F0); border-radius: 18px; max-width: 820px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px -10px rgba(0,0,0,0.5), 0 0 1px 1px rgba(255,255,255,0.1); overflow: hidden; box-sizing: border-box; animation: themeModalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Header -->
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border-color, #E2E8F0); display: flex; align-items: center; justify-content: space-between; background: var(--header-bg, #FFFFFF); flex-shrink: 0;">
            <div>
                <h3 style="margin: 0; font-size: 19px; font-weight: 800; color: var(--header-title, #0F172A); display: flex; align-items: center; gap: 8px;">
                    <span>🎨</span> Interface Theme Palette
                </h3>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--header-sub, #64748B); font-weight: 500;">
                    Select your preferred executive dashboard theme or classic color palette.
                </p>
            </div>
            <button type="button" onclick="closeThemePickerModal()" aria-label="Close" style="background: var(--dashboard-bg, #F1F5F9); border: 1px solid var(--border-color, #CBD5E1); color: var(--header-title, #0F172A); width: 34px; height: 34px; border-radius: 8px; font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='var(--brand-primary,#2563EB)';this.style.color='#FFFFFF'" onmouseout="this.style.background='var(--dashboard-bg,#F1F5F9)';this.style.color='var(--header-title,#0F172A)'">
                ✕
            </button>
        </div>

        <!-- Scrollable Theme Options Body -->
        <div style="padding: 22px 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 24px;">

            <!-- SECTION 1: REFERENCE DASHBOARD THEMES (Images 1 - 5) -->
            <div>
                <div style="font-size: 11px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase; color: var(--brand-primary, #2563EB); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <span>✨</span> Featured Reference Themes (New)
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px;">

                    <!-- 11: Student Result Dashboard -->
                    <button type="button" onclick="setUssTheme('student-navy')" class="theme-select-btn" data-theme-id="student-navy" style="text-align: left; padding: 12px 14px; border-radius: 12px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="display: flex; width: 34px; height: 22px; border-radius: 6px; overflow: hidden; border: 1px solid #CBD5E1;">
                                    <div style="width: 35%; background: #0C1427;"></div>
                                    <div style="width: 65%; background: #F3F6FC; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #2563EB;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 700; font-size: 13.5px; color: var(--header-title, #0F172A);">Student Result</span>
                            </div>
                            <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #2563EB; color: #FFFFFF; padding: 2px 7px; border-radius: 9999px;">ACTIVE</span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--header-sub, #64748B); line-height: 1.3;">
                            Navy sidebar, crisp light canvas & vibrant blue pill (Image 1)
                        </div>
                    </button>

                    <!-- 12: OripioFin -->
                    <button type="button" onclick="setUssTheme('oripiofin')" class="theme-select-btn" data-theme-id="oripiofin" style="text-align: left; padding: 12px 14px; border-radius: 12px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="display: flex; width: 34px; height: 22px; border-radius: 6px; overflow: hidden; border: 1px solid #CBD5E1;">
                                    <div style="width: 35%; background: #FFFFFF; border-right: 1px solid #E5E7EB;"></div>
                                    <div style="width: 65%; background: #F4F6F8; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #059669;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 700; font-size: 13.5px; color: var(--header-title, #0F172A);">OripioFin</span>
                            </div>
                            <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #059669; color: #FFFFFF; padding: 2px 7px; border-radius: 9999px;">ACTIVE</span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--header-sub, #64748B); line-height: 1.3;">
                            Minimalist white/slate canvas with emerald green accents (Image 2)
                        </div>
                    </button>

                    <!-- 13: Inventory Slate -->
                    <button type="button" onclick="setUssTheme('inventory-slate')" class="theme-select-btn" data-theme-id="inventory-slate" style="text-align: left; padding: 12px 14px; border-radius: 12px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="display: flex; width: 34px; height: 22px; border-radius: 6px; overflow: hidden; border: 1px solid #B8CFDC;">
                                    <div style="width: 35%; background: #1E3A4B;"></div>
                                    <div style="width: 65%; background: #DCE7EE; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #2D6A7D;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 700; font-size: 13.5px; color: var(--header-title, #0F172A);">Inventory Slate</span>
                            </div>
                            <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #2D6A7D; color: #FFFFFF; padding: 2px 7px; border-radius: 9999px;">ACTIVE</span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--header-sub, #64748B); line-height: 1.3;">
                            Deep petrol sidebar with powder blue canvas & charts (Image 3)
                        </div>
                    </button>

                    <!-- 14: Nexora CRM -->
                    <button type="button" onclick="setUssTheme('nexora-crm')" class="theme-select-btn" data-theme-id="nexora-crm" style="text-align: left; padding: 12px 14px; border-radius: 12px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="display: flex; width: 34px; height: 22px; border-radius: 6px; overflow: hidden; border: 1px solid #CBD5E1;">
                                    <div style="width: 35%; background: #0B162C;"></div>
                                    <div style="width: 65%; background: #F8FAFC; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #0EA5E9;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 700; font-size: 13.5px; color: var(--header-title, #0F172A);">Nexora CRM</span>
                            </div>
                            <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #0EA5E9; color: #FFFFFF; padding: 2px 7px; border-radius: 9999px;">ACTIVE</span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--header-sub, #64748B); line-height: 1.3;">
                            Midnight navy sidebar, slate-50 canvas & bright cyan (Image 4)
                        </div>
                    </button>

                    <!-- 15: EduSphere -->
                    <button type="button" onclick="setUssTheme('edusphere-indigo')" class="theme-select-btn" data-theme-id="edusphere-indigo" style="text-align: left; padding: 12px 14px; border-radius: 12px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="display: flex; width: 34px; height: 22px; border-radius: 6px; overflow: hidden; border: 1px solid #CBD5E1;">
                                    <div style="width: 35%; background: #0D1B3E;"></div>
                                    <div style="width: 65%; background: #F3F4FA; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #4F46E5;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 700; font-size: 13.5px; color: var(--header-title, #0F172A);">EduSphere</span>
                            </div>
                            <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #4F46E5; color: #FFFFFF; padding: 2px 7px; border-radius: 9999px;">ACTIVE</span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--header-sub, #64748B); line-height: 1.3;">
                            Royal navy sidebar with soft periwinkle & royal indigo (Image 5)
                        </div>
                    </button>

                </div>
            </div>

            <!-- SECTION 2: CLASSIC PALETTES (Existing Preserved) -->
            <div>
                <div style="font-size: 11px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase; color: var(--header-sub, #64748B); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <span>🛡️</span> Classic Project Themes
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px;">

                    <!-- 01: Black Dark -->
                    <button type="button" onclick="setUssTheme('dark')" class="theme-select-btn" data-theme-id="dark" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #000000; border: 1px solid #FFD43B; display: flex; align-items: center; justify-content: center; color: #FFD43B; font-size: 11px;">🌙</div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Black Dark</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Executive Midnight & Gold</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #FFD43B; color: #000000; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 02: White Light -->
                    <button type="button" onclick="setUssTheme('light')" class="theme-select-btn" data-theme-id="light" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #F1F5F9; border: 1px solid #1D4ED8; display: flex; align-items: center; justify-content: center; color: #1D4ED8; font-size: 11px;">☀️</div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">White Light</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Crisp Slate & Blue</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #1D4ED8; color: #FFFFFF; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 03: Navy Blue -->
                    <button type="button" onclick="setUssTheme('navy-blue')" class="theme-select-btn" data-theme-id="navy-blue" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #081426; border: 1px solid #FFD166;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Navy Blue</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Deep Ocean & Amber</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #FFD166; color: #081426; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 04: Royal Blue -->
                    <button type="button" onclick="setUssTheme('royal-blue')" class="theme-select-btn" data-theme-id="royal-blue" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #143599; border: 1px solid #FFE45E;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Royal Blue</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Vibrant Cobalt & Yellow</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #FFE45E; color: #143599; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 05: Deep Green -->
                    <button type="button" onclick="setUssTheme('deep-green')" class="theme-select-btn" data-theme-id="deep-green" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #043628; border: 1px solid #F4C95D;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Deep Green</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Forest Jade & Gold</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #F4C95D; color: #043628; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 06: Purple -->
                    <button type="button" onclick="setUssTheme('purple')" class="theme-select-btn" data-theme-id="purple" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #331063; border: 1px solid #FFD166;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Purple</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Imperial Violet & Gold</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #FFD166; color: #331063; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 07: Red -->
                    <button type="button" onclick="setUssTheme('red')" class="theme-select-btn" data-theme-id="red" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #570F0F; border: 1px solid #FFE066;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Red</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Crimson Command & Gold</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #FFE066; color: #570F0F; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 08: Orange -->
                    <button type="button" onclick="setUssTheme('orange')" class="theme-select-btn" data-theme-id="orange" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #FF5A1F; border: 1px solid #17202A;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Orange</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Radiant Solid Orange</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #FF5A1F; color: #FFFFFF; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 09: Yellow -->
                    <button type="button" onclick="setUssTheme('yellow')" class="theme-select-btn" data-theme-id="yellow" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #EAB308; border: 1px solid #111111;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Yellow</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Golden Amber & Dark</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #111111; color: #FFFFFF; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                    <!-- 10: Soft Pink -->
                    <button type="button" onclick="setUssTheme('soft-pink')" class="theme-select-btn" data-theme-id="soft-pink" style="text-align: left; padding: 10px 12px; border-radius: 10px; border: 2px solid var(--border-color, #E2E8F0); background: var(--card-bg, #FFFFFF); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 6px; background: #E89AA8; border: 1px solid #3A1823;"></div>
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--header-title, #0F172A);">Soft Pink</div>
                                <div style="font-size: 11px; color: var(--header-sub, #64748B);">Rose Quartz & Plum</div>
                            </div>
                        </div>
                        <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: #3A1823; color: #FFFFFF; padding: 2px 6px; border-radius: 9999px;">ACTIVE</span>
                    </button>

                </div>
            </div>

            <!-- SECTION 3: SYSTEM MATCH -->
            <div style="border-top: 1px solid var(--border-color, #E2E8F0); padding-top: 16px;">
                <button type="button" onclick="setUssTheme('system')" class="theme-select-btn" data-theme-id="system" style="width: 100%; text-align: left; padding: 12px 16px; border-radius: 12px; border: 2px dashed var(--border-color, #CBD5E1); background: var(--dashboard-bg, #F1F5F9); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 18px;">💻</span>
                        <div>
                            <div style="font-weight: 700; font-size: 13.5px; color: var(--header-title, #0F172A);">Match System Device Settings</div>
                            <div style="font-size: 11.5px; color: var(--header-sub, #64748B);">Automatically follows your OS dark/light mode preference</div>
                        </div>
                    </div>
                    <span class="active-pill" style="display: none; font-size: 10px; font-weight: 800; background: var(--header-title, #0F172A); color: var(--card-bg, #FFFFFF); padding: 3px 8px; border-radius: 9999px;">ACTIVE</span>
                </button>
            </div>

        </div>

        <!-- Footer -->
        <div style="padding: 14px 24px; border-top: 1px solid var(--border-color, #E2E8F0); display: flex; align-items: center; justify-content: flex-end; background: var(--header-bg, #FFFFFF); flex-shrink: 0;">
            <button type="button" onclick="closeThemePickerModal()" class="btn btn-secondary btn-sm" style="font-weight: 600; padding: 7px 18px; border-radius: 8px; cursor: pointer;">
                Done
            </button>
        </div>

    </div>
</div>

<style>
@keyframes themeModalPop {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.theme-select-btn:hover {
    border-color: var(--brand-primary, #2563EB) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.theme-select-btn.is-active {
    border-color: var(--brand-primary, #2563EB) !important;
    background: rgba(37, 99, 235, 0.04) !important;
}
.theme-select-btn.is-active .active-pill {
    display: inline-block !important;
}
</style>

<script>
var USS_THEME_NAMES = {
    'student-navy': 'Student Result',
    'oripiofin': 'OripioFin',
    'inventory-slate': 'Inventory Slate',
    'nexora-crm': 'Nexora CRM',
    'edusphere-indigo': 'EduSphere',
    'dark': 'Black Dark',
    'light': 'White Light',
    'navy-blue': 'Navy Blue',
    'royal-blue': 'Royal Blue',
    'deep-green': 'Deep Green',
    'purple': 'Purple',
    'red': 'Red',
    'orange': 'Orange',
    'yellow': 'Yellow',
    'soft-pink': 'Soft Pink',
    'system': 'System Device'
};

function refreshThemeModalState() {
    var pref = localStorage.getItem('uss_theme') || 'dark';
    var buttons = document.querySelectorAll('.theme-select-btn');
    buttons.forEach(function(btn) {
        var id = btn.getAttribute('data-theme-id');
        if (id === pref) {
            btn.classList.add('is-active');
            var pill = btn.querySelector('.active-pill');
            if (pill) pill.style.display = 'inline-block';
        } else {
            btn.classList.remove('is-active');
            var pill = btn.querySelector('.active-pill');
            if (pill) pill.style.display = 'none';
        }
    });

    var label = document.getElementById('activeThemeNameLabel');
    if (label) {
        label.textContent = USS_THEME_NAMES[pref] || 'Theme';
    }
}

function openThemePaletteModal() {
    refreshThemeModalState();
    var m = document.getElementById('themePickerModal');
    if (m) {
        m.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeThemePickerModal() {
    var m = document.getElementById('themePickerModal');
    if (m) {
        m.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function setUssTheme(mode) {
    localStorage.setItem('uss_theme', mode);
    var actual = mode;
    if (mode === 'system') {
        actual = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', actual);
    document.documentElement.setAttribute('data-theme-preference', mode);
    refreshThemeModalState();
    closeThemePickerModal();
}

// Keyboard ESC to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeThemePickerModal();
    }
});

// Run state initialization on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshThemeModalState);
} else {
    refreshThemeModalState();
}
</script>
