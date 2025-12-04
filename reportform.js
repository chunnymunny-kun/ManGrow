document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('measurements-container');
    const addBtn = document.getElementById('add-measurement-btn');
    const speciesSelect = document.getElementById('mangrove_species');
    const form = document.querySelector('form'); // Assuming your form is the only one
    let measurementCount = 0;
    const maxMeasurements = 5;
    let currentMethod = document.querySelector('input[name="measurement_type"]:checked').value;
    let currentSpecies = '';
    
    // Track measurement method changes
    document.querySelectorAll('input[name="measurement_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            currentMethod = this.value;
            updateMeasurementFields();
            updateAverage();
        });
    });
    
    // Track species selection
    speciesSelect.addEventListener('change', function() {
        currentSpecies = this.value;
    });
    
    // Add new measurement
    addBtn.addEventListener('click', function() {
        if (measurementCount >= maxMeasurements) {
            alert(`Maximum ${maxMeasurements} measurements allowed`);
            return;
        }
        
        if (!currentSpecies) {
            alert('Please select a mangrove species first');
            return;
        }
        
        measurementCount++;
        const measurementDiv = document.createElement('div');
        measurementDiv.className = 'measurement-item';
        measurementDiv.dataset.index = measurementCount;
        measurementDiv.innerHTML = `
            <div class="form-group">
                <label>Measurement #${measurementCount}</label>
                <span class="species-tag">${speciesSelect.options[speciesSelect.selectedIndex].text}</span>
            </div>
            
            <div class="form-group ${currentMethod === 'visual_estimate' ? '' : 'hidden'} visual-field">
                <label>Height Estimate</label>
                <select name="height_estimate_${measurementCount}">
                    <option value="">Select range</option>
                    <option value="0.25">0-0.5m (Knee height)</option>
                    <option value="0.75">0.5-1m (Waist height)</option>
                    <option value="1.5">1-2m (Chest height)</option>
                    <option value="2.5">2-3m (Above head)</option>
                    <option value="4">3-5m (1-story)</option>
                </select>
            </div>
            
            <div class="form-group ${currentMethod === 'height_pole' ? '' : 'hidden'} exact-field">
                <label>Exact Height (meters)</label>
                <input type="number" name="exact_height_${measurementCount}" min="0.1" step="0.01" placeholder="1.5">
            </div>
            
            <button type="button" class="remove-btn">Remove</button>
            <hr>
        `;
        
        container.appendChild(measurementDiv);
        updateAverage();
    });
    
    // Use event delegation for dynamic elements
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-btn')) {
            const item = e.target.closest('.measurement-item');
            container.removeChild(item);
            measurementCount--;
            renumberMeasurements();
            updateAverage();
        }
    });
    
    // Update all measurement fields when method changes
    function updateMeasurementFields() {
        document.querySelectorAll('.measurement-item').forEach(item => {
            const index = item.dataset.index;
            const visualField = item.querySelector(`.visual-field`);
            const exactField = item.querySelector(`.exact-field`);
            
            visualField.classList.toggle('hidden', currentMethod !== 'visual_estimate');
            exactField.classList.toggle('hidden', currentMethod !== 'height_pole');
        });
    }
    
    // Renumber measurements after deletion
    function renumberMeasurements() {
        document.querySelectorAll('.measurement-item').forEach((item, index) => {
            const label = item.querySelector('label');
            if (label) {
                label.textContent = `Measurement #${index + 1}`;
            }
            item.dataset.index = index + 1;
        });
    }
    
    // Calculate average height
    function updateAverage() {
        const heights = [];
        
        document.querySelectorAll('.measurement-item').forEach(item => {
            const index = item.dataset.index;
            let height = 0;
            
            if (currentMethod === 'visual_estimate') {
                const select = item.querySelector(`select[name="height_estimate_${index}"]`);
                height = select ? parseFloat(select.value) || 0 : 0;
            } 
            else if (currentMethod === 'height_pole') {
                const input = item.querySelector(`input[name="exact_height_${index}"]`);
                height = input ? parseFloat(input.value) || 0 : 0;
            }
            
            if (height > 0) heights.push(height);
        });
        
        const avgDisplay = document.getElementById('avg-height-value');
        const avgInput = document.getElementById('avg-height-input');
        
        if (heights.length > 0) {
            const avg = heights.reduce((a, b) => a + b, 0) / heights.length;
            avgDisplay.textContent = avg.toFixed(2) + ' meters';
            avgInput.value = avg.toFixed(2);
        } else {
            avgDisplay.textContent = 'No measurements yet';
            avgInput.value = '';
        }
    }
    
    // Recalculate when any height field changes
    container.addEventListener('change', function(e) {
        if (e.target.name.startsWith('height_estimate_') || e.target.name.startsWith('exact_height_')) {
            updateAverage();
        }
    });
    
    // Form validation before submit
    form.addEventListener('submit', function(e) {
        if (measurementCount === 0) {
            e.preventDefault();
            alert('Please add at least one measurement');
            return;
        }
        
        const avgHeight = parseFloat(document.getElementById('avg-height-input').value);
        if (isNaN(avgHeight) || avgHeight <= 0) {
            e.preventDefault();
            alert('Please complete all measurements and ensure valid heights');
            return;
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const otherCheckbox = document.getElementById('other-species-checkbox');
    const otherSpeciesField = document.querySelector('.other-species');
    
    otherCheckbox.addEventListener('change', function() {
        if (this.checked) {
            otherSpeciesField.style.display = 'block';
            otherSpeciesField.querySelector('input').required = true;
        } else {
            otherSpeciesField.style.display = 'none';
            otherSpeciesField.querySelector('input').required = false;
            otherSpeciesField.querySelector('input').value = '';
        }
    });
});
//can be removed
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="human_activities[]"]');
    const noneCheckbox = document.querySelector('input[name="human_activities[]"][value="None"]');

    // Add event listener to all checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this === noneCheckbox) {
                // If "None" was checked, uncheck all others
                if (this.checked) {
                    checkboxes.forEach(cb => {
                        if (cb !== noneCheckbox) cb.checked = false;
                    });
                }
            } else {
                // If any other checkbox was checked, uncheck "None"
                if (this.checked) {
                    noneCheckbox.checked = false;
                }

                // If all other checkboxes are unchecked, check "None"
                const anyChecked = Array.from(checkboxes)
                    .filter(cb => cb !== noneCheckbox)
                    .some(cb => cb.checked);

                noneCheckbox.checked = !anyChecked;
            }
        });
    });
});
