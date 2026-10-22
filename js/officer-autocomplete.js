(() => {
    const options = document.getElementById('officerNameOptions');
    if (!options) return;
    const officerNames = JSON.parse(options.textContent);
    const positionOptions = document.getElementById('officerPositionOptions');
    const officerPositions = positionOptions ? JSON.parse(positionOptions.textContent) : {};
    function selectOfficer(input, name) {
        input.value = name;
        const designation = document.getElementById(input.dataset.designationTarget || '');
        if (designation) {
            designation.value = officerPositions[name] || '';
            designation.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    function closeOtherDropdowns(activeInput) {
        document.querySelectorAll('[data-officer-autocomplete]').forEach(field => {
            if (field !== activeInput) {
                const menu = document.getElementById(field.id + '_dropdown');
                if (menu) menu.style.display = 'none';
            }
        });
    }
    document.addEventListener('focusin', event => closeOtherDropdowns(event.target));
        function setupAutocomplete(inputId, dropdownId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            
            if (!input || !dropdown) return;

            let wasOpenOnPointerDown = false;
            input.addEventListener('pointerdown', () => {
                wasOpenOnPointerDown = dropdown.style.display === 'block';
                closeOtherDropdowns(input);
            });

            // Show dropdown on focus
            input.addEventListener('focus', function() {
                if (this.value.trim() === '') {
                    showAllSuggestions(dropdown, input);
                } else {
                    filterSuggestions(this.value, dropdown, input);
                }
            });

            // First click opens; a second click on the same field closes.
            input.addEventListener('click', function(e) {
                closeOtherDropdowns(input);
                const shouldClose = e.detail === 0 ? dropdown.style.display === 'block' : wasOpenOnPointerDown;
                wasOpenOnPointerDown = false;
                if (shouldClose) {
                    dropdown.style.display = 'none';
                } else if (this.value.trim() === '') {
                    showAllSuggestions(dropdown, input);
                } else {
                    filterSuggestions(this.value, dropdown, input);
                }
            });
            // Filter on input
            input.addEventListener('input', function() {
                const value = this.value;
                if (value.trim() === '') {
                    showAllSuggestions(dropdown, input);
                } else {
                    filterSuggestions(value, dropdown, input);
                }
            });

            // Handle keyboard navigation
            input.addEventListener('keydown', function(e) {
                if (dropdown.style.display !== 'block') return;

                const items = Array.from(dropdown.querySelectorAll('.autocomplete-item:not([style*="cursor: default"])'));
                if (items.length === 0) return;

                const selectedItem = dropdown.querySelector('.autocomplete-item.selected');
                let currentIndex = selectedItem ? items.indexOf(selectedItem) : -1;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        currentIndex = (currentIndex + 1) % items.length;
                        highlightItem(items, currentIndex, dropdown);
                        break;
                    
                    case 'ArrowUp':
                        e.preventDefault();
                        currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
                        highlightItem(items, currentIndex, dropdown);
                        break;
                    
                    case 'Enter':
                        e.preventDefault();
                        if (selectedItem) {
                            const text = selectedItem.textContent || selectedItem.innerText;
                            selectOfficer(input, text);
                            dropdown.style.display = 'none';
                        }
                        break;
                    
                    case 'Tab':
                        const itemToSelect = selectedItem || items[0];
                        if (itemToSelect) {
                            const text = itemToSelect.textContent || itemToSelect.innerText;
                            selectOfficer(input, text);
                            dropdown.style.display = 'none';
                        }
                        break;
                    
                    case 'Escape':
                        dropdown.style.display = 'none';
                        break;
                }
            });

            // Prevent clicks inside dropdown from closing it
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }

        // Highlight selected item and scroll into view
        function highlightItem(items, index, dropdown) {
            items.forEach(item => item.classList.remove('selected'));
            
            if (index >= 0 && index < items.length) {
                items[index].classList.add('selected');
                
                const item = items[index];
                const dropdownRect = dropdown.getBoundingClientRect();
                const itemRect = item.getBoundingClientRect();
                
                if (itemRect.bottom > dropdownRect.bottom) {
                    item.scrollIntoView({ block: 'end', behavior: 'smooth' });
                } else if (itemRect.top < dropdownRect.top) {
                    item.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            }
        }

        function showAllSuggestions(dropdown, input) {
            dropdown.innerHTML = '';
            
            if (officerNames.length === 0) {
                dropdown.innerHTML = '<div class="autocomplete-item" style="color: #999; cursor: default;">No officers available</div>';
                dropdown.style.display = 'block';
                return;
            }

            officerNames.forEach(name => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = name;
                item.addEventListener('click', function() {
                    selectOfficer(input, name);
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            
            dropdown.style.display = 'block';
        }

        function filterSuggestions(value, dropdown, input) {
            dropdown.innerHTML = '';
            const searchValue = value.toLowerCase();
            
            const filtered = officerNames.filter(name => 
                name.toLowerCase().includes(searchValue)
            );

            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="autocomplete-item" style="color: #999; cursor: default;">No matches found</div>';
                dropdown.style.display = 'block';
                return;
            }

            filtered.forEach(name => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                
                const index = name.toLowerCase().indexOf(searchValue);
                if (index !== -1) {
                    const before = name.substring(0, index);
                    const match = name.substring(index, index + searchValue.length);
                    const after = name.substring(index + searchValue.length);
                    const highlighted = document.createElement('strong');
                    highlighted.textContent = match;
                    item.append(document.createTextNode(before), highlighted, document.createTextNode(after));
                } else {
                    item.textContent = name;
                }
                
                item.addEventListener('click', function() {
                    selectOfficer(input, name);
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            
            dropdown.style.display = 'block';
        }


    document.querySelectorAll('[data-officer-autocomplete]').forEach(input => setupAutocomplete(input.id, input.id + '_dropdown'));
})();
