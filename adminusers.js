function importFormToggle(ID) {
    var form = document.getElementById(ID);
    form.style.display = form.style.display === "none" ? "block" : "none";
}

function getBarangays(cityDropdown, tableType) {
    let selectedCity = cityDropdown.value;
    let form = cityDropdown.closest('form');
    let barangayDropdown = form.querySelector('.barangay-filter');
    
    // Reset barangay dropdown when city changes
    barangayDropdown.innerHTML = '<option value="">All</option>';
    
    if(selectedCity.trim() === "") {
        barangayDropdown.disabled = true;
        barangayDropdown.selectedIndex = 0;
        filterTable(tableType);
        return false;
    }
    
    // Enable barangay dropdown and fetch barangays
    barangayDropdown.disabled = false;
    
    fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`)
    .then(response => {
        if(!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(function(barangays) {
        let out = '<option value="">All</option>';
        
        if(barangays.error) {
            console.error(barangays.error);
            return;
        }
        
        barangays.forEach(barangay => {
            out += `<option value="${barangay['barangay']}">${barangay['barangay']}</option>`;
        });

        barangayDropdown.innerHTML = out;
        filterTable(tableType);
    })
    .catch(error => {
        console.error('Error:', error);
        barangayDropdown.innerHTML = '<option value="">Error loading barangays</option>';
    });
}

function filterTable(tableType) {
    let form = document.querySelector(`form[name="${tableType}-account-filters"]`);
    if (!form) {
        console.error(`Form for ${tableType} accounts not found`);
        return;
    }
    
    let cityDropdown = form.querySelector('.city-filter');
    let barangayDropdown = form.querySelector('.barangay-filter');
    let selectedCity = cityDropdown ? cityDropdown.value : '';
    let selectedBarangay = barangayDropdown ? barangayDropdown.value : '';
    
    // Get current page from URL
    let urlParams = new URLSearchParams(window.location.search);
    let currentPage = urlParams.get('page') || 1;

    let tableBody = document.getElementById(`${tableType}-accounts-body`);
    if (!tableBody) {
        console.error(`Table body for ${tableType} accounts not found`);
        return;
    }
    
    tableBody.innerHTML = '<tr><td colspan="' + (tableType === 'temp' ? 12 : 10) + '" class="text-center">Loading...</td></tr>';

    // Build the fetch URL with pagination and filters
    let url = `adminshowtabledata.php?action=filter&table=${tableType}&page=${currentPage}`;
    if (selectedCity) {
        url += `&city=${encodeURIComponent(selectedCity)}`;
    }
    if (selectedBarangay) {
        url += `&barangay=${encodeURIComponent(selectedBarangay)}`;
    }

    // Add access role filter if current user is a Barangay Official
    if (document.body.dataset.accessrole === 'Barangay Official') {
        const barangay = document.body.dataset.barangay;
        if (barangay) {
            url += `&barangay=${encodeURIComponent(barangay)}`;
            if (cityDropdown) cityDropdown.disabled = true;
            if (barangayDropdown) barangayDropdown.disabled = true;
        }
    }
    
    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data || !Array.isArray(data)) {
            throw new Error('Invalid data format received');
        }
        updateTable(data, tableType);
    })
    .catch(error => {
        console.error('Error:', error);
        tableBody.innerHTML = `<tr><td colspan="${tableType === 'temp' ? 12 : 10}" class="text-center">Error loading data: ${error.message}</td></tr>`;
    });
}

function updateTable(data, tableType) {
    let tableBody = document.getElementById(`${tableType}-accounts-body`);
    if (!tableBody) return;
    
    let html = '';
    
    if (data.length === 0) {
        html = `<tr><td colspan="${tableType === 'temp' ? 12 : 10}" class="text-center">No records found</td></tr>`;
    } else {
        data.forEach(item => {
            if (tableType === 'temp') {
                html += `
                    <tr>
                        <td>${item.firstname || ''}</td>
                        <td>${item.lastname || ''}</td>
                        <td>${item.email || ''}</td>
                        <td>${item.personal_email || ''}</td>
                        <td>${item.barangay || ''}</td>
                        <td>${item.city_municipality || ''}</td>
                        <td>${item.accessrole || ''}</td>
                        <td>${item.organization || ''}</td>
                        <td>${item.is_verified || ''}</td>
                        <td>${item.import_date || ''}</td>
                        <td>${item.imported_by || ''}</td>
                        <td>
                            <button class="btn btn-primary btn-sm temp-accounts-edit-btn" data-id="${item.tempacc_id || ''}" onclick="editTempAccount('${item.tempacc_id || ''}')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm temp-accounts-delete-btn" data-id="${item.tempacc_id || ''}" onclick="deleteTempAccount('${item.tempacc_id || ''}')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </td>
                    </tr>
                `;
            } else {
                html += `
                    <tr>
                        <td>${item.fullname || ''}</td>
                        <td>${item.email || ''}</td>
                        <td>${item.personal_email || ''}</td>
                        <td>${item.barangay || ''}</td>
                        <td>${item.city_municipality || ''}</td>
                        <td>${item.accessrole || ''}</td>
                        <td>${item.organization || ''}</td>
                        <td>${item.date_registered || ''}</td>
                        <td>${item.bio ? (item.bio.length > 50 ? item.bio.substring(0, 50) + '...' : item.bio) : ''}</td>
                        <td>
                            <button class="btn btn-primary btn-sm valid-accounts-edit-btn" data-id="${item.account_id || ''}" onclick="editValidAccount('${item.account_id || ''}')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm valid-accounts-delete-btn" data-id="${item.account_id || ''}" onclick="deleteValidAccount('${item.account_id || ''}')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </td>
                    </tr>
                `;
            }
        });
    }
    
    tableBody.innerHTML = html;
}

// Initialize tables on page load
document.addEventListener('DOMContentLoaded', function() {
    filterTable('temp');
    filterTable('verified');
});