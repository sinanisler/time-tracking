# Manual Fix Required - Icon Layout

## Problem
The TODO list section in `class-tt-calendar.php` needs to have ALL icons on the LEFT side.

## Current Layout (WRONG):
[✓] TODO text [≡][🗑️]

## Desired Layout (CORRECT):
```
[✓]  TODO text content
[≡]  taking all remaining 
[🗑️] space on the right
```

## The File is in: 
`TODO_LIST_SECTION_CORRECTED.html`

## Please copy that HTML and replace lines 613-716 in class-tt-calendar.php

The corrected HTML shows:
- Left column with flex-col (vertical) containing checkbox, drag, delete icons
- Right column with flex-1 for text content

This gives you the compact left icon column you wanted!
