---
name: Administrative Professional
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#44474e'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#74777f'
  outline-variant: '#c4c6cf'
  surface-tint: '#465f88'
  primary: '#002046'
  on-primary: '#ffffff'
  primary-container: '#1b365d'
  on-primary-container: '#87a0cd'
  inverse-primary: '#aec7f7'
  secondary: '#046d3f'
  on-secondary: '#ffffff'
  secondary-container: '#9af3b8'
  on-secondary-container: '#0f7142'
  tertiary: '#361900'
  on-tertiary: '#ffffff'
  tertiary-container: '#552b00'
  on-tertiary-container: '#eb851c'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e3ff'
  primary-fixed-dim: '#aec7f7'
  on-primary-fixed: '#001b3d'
  on-primary-fixed-variant: '#2e476f'
  secondary-fixed: '#9df5bb'
  secondary-fixed-dim: '#81d9a0'
  on-secondary-fixed: '#00210f'
  on-secondary-fixed-variant: '#00522e'
  tertiary-fixed: '#ffdcc3'
  tertiary-fixed-dim: '#ffb77d'
  on-tertiary-fixed: '#2f1500'
  on-tertiary-fixed-variant: '#6e3900'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  headline-lg:
    fontFamily: Inter
    fontSize: 30px
    fontWeight: '700'
    lineHeight: 38px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
  mono-data:
    fontFamily: monospace
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  container-max: 1280px
  gutter: 16px
---

## Brand & Style
The design system is engineered for efficiency, accountability, and clarity within the context of university student finance administration (Fachschaften and AStA). The brand personality is **authoritative yet accessible**, emphasizing a high-trust environment where financial data is handled with precision.

The design style follows a **Modern Corporate** aesthetic with a lean toward **Minimalism**. It prioritizes information density without sacrificing legibility. The visual language avoids decorative flourishes in favor of structural integrity, utilizing subtle borders and clear containment to distinguish between different stages of the financial workflow—moving from initial resolution (Beschluss) to final payment (Zahlungsanweisung).

## Colors
The palette is rooted in professional stability and semantic clarity.

- **Primary (Deep Blue):** Used for navigation, headers, and primary actions. It evokes the official nature of university administration.
- **Success (Emerald Green):** Specifically reserved for "Executed" or "Paid" statuses and final approvals.
- **Warning (Amber):** Identifies items requiring attention, pending reviews, or necessary corrections.
- **Danger (Soft Red):** Marks rejected applications, cancelled payments, or critical errors.
- **Surface (Off-White/Light Gray):** The primary background is tinted slightly cool to reduce glare during long administrative sessions. 
- **Contrast:** High-contrast text (#1E293B) is used for all data points to ensure readability against light surfaces.

## Typography
This design system utilizes **Inter** for its exceptional legibility in data-heavy environments. The type scale is intentionally tight to allow for higher information density in complex tables and forms.

- **Headlines:** Use Bold/Semi-Bold weights to clearly demarcate sections like "Beschluss-Details" vs "Zahlungsanweisung."
- **Body Text:** Standardized at 14px for optimal balance between density and readability.
- **Labels:** Uppercase and tracked out slightly to differentiate field headers from user input.
- **Monospace:** Use standard system monospace for IBANs, transaction IDs, and budget codes to prevent character confusion (e.g., 0 vs O).

## Layout & Spacing
The layout uses a **Fixed Grid** approach for desktop administration to ensure data tables remain predictable and easy to scan.

- **Grid Model:** 12-column system with a maximum container width of 1280px.
- **Density:** We utilize a "Compact" spacing rhythm. Vertical rhythm is built on 4px increments, allowing for high-density forms and tables.
- **Sidebars:** A fixed left-hand navigation (240px) provides persistent access to "Resolutions," "Payments," and "Budget Overview."
- **Mobile:** On smaller screens, the grid collapses to a single column with 16px side margins. Tables should transition to "Card" views or implement horizontal scrolling with sticky first columns.

## Elevation & Depth
Depth is signaled through **Tonal Layers** and **Low-Contrast Outlines** rather than heavy shadows. This maintains the "Administrative Professional" look without feeling overly "app-like."

- **Level 0 (Background):** #F8FAFC (Main canvas).
- **Level 1 (Cards/Containers):** Pure white (#FFFFFF) with a 1px solid border (#E2E8F0). No shadow.
- **Level 2 (Modals/Popovers):** Pure white with a very soft, diffused shadow (0px 4px 12px rgba(0,0,0,0.05)) to indicate focus.
- **Contextual Separation:** "Zahlungsanweisung" containers use a subtle left-border accent in the Primary color to distinguish them from "Beschluss" sections on the same page.

## Shapes
The shape language is **Soft** but disciplined. 

- **Standard Elements:** 0.25rem (4px) corner radius for input fields, buttons, and status badges.
- **Large Containers:** 0.5rem (8px) for cards and main content areas.
- **Status Pills:** Fully rounded (pill-shaped) to distinguish them from interactive buttons.
- **Visual Logic:** Sharp corners (0px) are used only for table row hover states and vertical separators to maintain a "grid" feel.

## Components
Consistent implementation of these components ensures workflow clarity.

- **Data Tables:** High-density with 8px vertical cell padding. Header cells have a light gray background (#F1F5F9). Alternate row striping is mandatory for horizontal scanning.
- **Status Badges:** Small, caps-lock text with a subtle background tint of the semantic color (e.g., Emerald tint with dark green text for "Executed").
- **Workflow Progress Trackers:** A horizontal stepper at the top of application views, showing stages: *Draft → Resolution → Payment → Archived*.
- **Action Cards:** Contextual boxes with a distinctive colored top-border (Primary for Info, Secondary for Completion) to group related actions.
- **Input Fields:** 1px border with a 2px blue focus ring. Labels are always positioned above the input, never as placeholders.
- **Buttons:** Primary buttons are solid Deep Blue. Secondary buttons use the "Ghost" style (border only) to maintain hierarchy.