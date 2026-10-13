<?php
// ============================================
// ROW HIGHLIGHTING SYSTEM - includes/highlighter.php
// Include this file before </body> tag in any page with tables
// Usage: include 'includes/highlighter.php';
// ============================================
?>

<!-- Row Highlighting CSS -->
<style>
/* Active row styling - NO CONTENT SHIFT */
tr.table-active-row {
    background-color: #fff3cd !important;
    border-left: 4px solid #ffc107 !important;
    box-shadow: 0 0 15px rgba(255, 193, 7, 0.4) !important;
    position: relative;
    transition: all 0.3s ease;
}

/* Arrow indicator - ABSOLUTE POSITIONED, no content shift */
tr.table-active-row::after {
    content: '▶';
    position: absolute;
    left: 8px; /* Inside the row, not outside */
    top: 50%;
    transform: translateY(-50%);
    color: #ffc107;
    font-size: 16px;
    animation: bounceArrow 1s infinite;
    z-index: 100;
    pointer-events: none; /* Don't interfere with clicks */
    text-shadow: 0 0 3px rgba(0,0,0,0.3);
    background: rgba(255,255,255,0.9);
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    border-radius: 50%;
}

tr.table-active-row:hover {
    background-color: #ffeeba !important;
}

@keyframes bounceArrow {
    0%, 100% { transform: translateY(-50%) translateX(0); }
    50% { transform: translateY(-50%) translateX(3px); }
}

/* Pulse animation on selection */
@keyframes pulseRow {
    0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.6); }
    70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
}

tr.table-active-row.pulse {
    animation: pulseRow 0.8s ease;
}

/* Floating indicator */
#workingOnIndicator {
    position: fixed;
    top: 80px;
    right: 20px;
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #000;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    z-index: 9999;
    font-weight: bold;
    font-size: 13px;
    border-left: 4px solid #dc3545;
    display: none;
    animation: slideInRight 0.3s ease;
    max-width: 250px;
}

#workingOnIndicator .invoice-num {
    font-size: 1.3em;
    color: #dc3545;
    margin: 0 3px;
}

#workingOnIndicator .working-action {
    display: block;
    margin-top: 3px;
    font-size: 12px;
    font-weight: 600;
    color: #5c3a00;
    background: rgba(255,255,255,0.55);
    padding: 2px 8px;
    border-radius: 10px;
    width: fit-content;
}
#workingOnIndicator .working-action:empty { display: none; }

#workingOnIndicator .close-btn {
    background: rgba(0,0,0,0.2);
    border: none;
    margin-left: 10px;
    cursor: pointer;
    font-size: 1.2em;
    color: #000;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    line-height: 1;
    transition: all 0.2s;
    float: right;
}

#workingOnIndicator .close-btn:hover {
    background: rgba(0,0,0,0.4);
    color: #fff;
}

@keyframes slideInRight {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Modal invoice badge */
.modal-title .invoice-badge {
    background: #ffc107;
    color: #000;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.7em;
    margin-left: 8px;
    vertical-align: middle;
}

/* Ensure table cells don't shift */
table.datatable tr.table-active-row td {
    position: relative;
}
</style>

<!-- Floating Indicator HTML -->
<div id="workingOnIndicator">
    <i class="ti ti-eye"></i> Working on Invoice <span class="invoice-num">#<span id="activeInvoiceNum"></span></span>
    <button class="close-btn" onclick="clearActiveRow()" title="Clear selection">×</button>
    <span class="working-action" id="activeInvoiceAction"></span>
</div>

<!-- Row Highlighting JavaScript -->
<script>
$(document).ready(function() {
    
    var activeInvoiceId = null;
    var modalJustClosed = false;
    
    // ============================================
    // CORE FUNCTIONS
    // ============================================
    
    // Extract invoice ID from any element
    function getInvoiceIdFromElement($elem) {
        var invoiceId = null;
        
        // Check data attributes
        invoiceId = $elem.data('invoice-id') || $elem.attr('data-invoice-id');
        if (invoiceId) return invoiceId;
        
        // Check onclick attribute
        var onclick = $elem.attr('onclick') || '';
        var match = onclick.match(/(\d+)/);
        if (match) return match[1];
        
        // Check data-bs-target (e.g., #addpayment123)
        var target = $elem.attr('data-bs-target') || $elem.attr('data-target') || '';
        match = target.match(/(\d+)/);
        if (match) return match[1];
        
        // Check href (supports ?invoice_id=N, /N/, and PDF filenames like INV-N-MemID-...)
        var href = $elem.attr('href') || '';
        match = href.match(/invoice_id=(\d+)/) || href.match(/INV-(\d+)/i) || href.match(/\/(\d+)\//);
        if (match) return match[1];
        
        // Check id attribute
        var id = $elem.attr('id') || '';
        match = id.match(/(\d+)/);
        if (match) return match[1];
        
        // Check parent modal
        var $modal = $elem.closest('.modal');
        if ($modal.length) {
            var modalId = $modal.attr('id') || '';
            match = modalId.match(/(\d+)/);
            if (match) return match[1];
        }
        
        // Check form inputs
        var $form = $elem.closest('form');
        if ($form.length) {
            var $input = $form.find('input[name="invoice_id"]');
            if ($input.length) return $input.val();
        }
        
        return null;
    }
    
    // Pull a friendly action label from a clicked button.
    // Priority: data-action-label  >  inner .tooltiptext1 span  >  title attr.
    function getActionLabel($elem) {
        if (!$elem || !$elem.length) return '';
        var label = $elem.attr('data-action-label');
        if (label) return label.trim();

        var $tip = $elem.find('.tooltiptext1').first();
        if ($tip.length) {
            label = ($tip.text() || '').trim();
            if (label) return label;
        }

        label = ($elem.attr('title') || '').trim();
        if (label) return label;

        // Fall back to the button's own visible text (helps for buttons like "Manual SMS").
        var ownText = $elem.clone().children().remove().end().text().trim();
        return ownText;
    }

    var activeActionLabel = '';

    // Highlight row by invoice ID
    function highlightRow(invoiceId, scrollToRow = true, actionLabel = null) {
        if (!invoiceId) return;
        
        // Remove existing highlights
        $('tr.table-active-row').removeClass('table-active-row pulse');
        
        // Find row containing this invoice ID
        var $targetRow = null;
        
        // Method 1: Look for buttons with this ID in their attributes
        $('table.datatable tbody tr').each(function() {
            var $row = $(this);
            var rowText = $row.html();
            
            if (rowText.includes('invoice_id=' + invoiceId) || 
                rowText.includes('value="' + invoiceId + '"') ||
                rowText.includes('#addpayment' + invoiceId) ||
                rowText.includes('#editInvoiceModal' + invoiceId) ||
                rowText.includes('gethistory(' + invoiceId + ')') ||
                rowText.includes('sendReminder(' + invoiceId + ')') ||
                rowText.includes('sendWhatsAppReminder(' + invoiceId + ')') ||
                rowText.includes('>' + invoiceId + '<')) {
                $targetRow = $row;
                return false;
            }
        });
        
        if (!$targetRow || !$targetRow.length) {
            // Method 2: Check if any cell contains just this number
            $('table.datatable tbody tr td:nth-child(2)').each(function() {
                if ($(this).text().trim() === invoiceId) {
                    $targetRow = $(this).closest('tr');
                    return false;
                }
            });
        }
        
        if ($targetRow && $targetRow.length) {
            $targetRow.addClass('table-active-row pulse');
            activeInvoiceId = invoiceId;
            
            // Store for persistence
            sessionStorage.setItem('activeInvoiceId', invoiceId);
            sessionStorage.setItem('activeInvoiceTime', Date.now());
            
            // Scroll into view
            if (scrollToRow) {
                var rowTop = $targetRow.offset().top;
                var windowTop = $(window).scrollTop();
                var windowBottom = windowTop + $(window).height();
                
                // Only scroll if row is not fully visible
                if (rowTop < windowTop || rowTop > windowBottom - 100) {
                    $('html, body').animate({ 
                        scrollTop: rowTop - 100 
                    }, 300);
                }
            }
            
            // Remove pulse after animation
            setTimeout(function() {
                $targetRow.removeClass('pulse');
            }, 800);
            
            // Show floating indicator
            if (actionLabel !== null) activeActionLabel = actionLabel || '';
            showFloatingIndicator(invoiceId, activeActionLabel);

            return true;
        }

        return false;
    }

    // Show floating indicator
    function showFloatingIndicator(invoiceId, actionLabel) {
        $('#activeInvoiceNum').text(invoiceId);
        $('#activeInvoiceAction').text(actionLabel || '');
        $('#workingOnIndicator').fadeIn(300);
    }

    // Clear active row (global function)
    window.clearActiveRow = function() {
        $('tr.table-active-row').removeClass('table-active-row');
        $('#workingOnIndicator').fadeOut(200);
        activeInvoiceId = null;
        activeActionLabel = '';
        sessionStorage.removeItem('activeInvoiceId');
        sessionStorage.removeItem('activeInvoiceAction');
        sessionStorage.removeItem('activeInvoiceTime');
        modalJustClosed = false;
    };
    
    // ============================================
    // EVENT HANDLERS
    // ============================================
    
    // Handle clicks on table buttons/links
    $(document).on('click', 'table.datatable button, table.datatable a, table.datatable .btn', function(e) {
        var $btn = $(this);
        var invoiceId = getInvoiceIdFromElement($btn);
        var label = getActionLabel($btn);

        if (invoiceId) {
            // Always refresh the action label, even if the same invoice is already active.
            highlightRow(invoiceId, activeInvoiceId !== invoiceId, label);
            sessionStorage.setItem('activeInvoiceAction', label || '');
        }
    });

    // Handle modal show - capture the triggering element
    $(document).on('show.bs.modal', '.modal', function(e) {
        var $trigger = $(e.relatedTarget);
        var invoiceId = getInvoiceIdFromElement($trigger);
        var label = getActionLabel($trigger);

        if (invoiceId) {
            highlightRow(invoiceId, false, label);
            sessionStorage.setItem('activeInvoiceAction', label || '');
            modalJustClosed = false;
        }

        // Add badge to modal title
        var $modal = $(this);
        var $title = $modal.find('.modal-title');
        $title.find('.invoice-badge').remove();

        if (invoiceId) {
            $title.append('<span class="invoice-badge">#' + invoiceId + '</span>');
        }
    });
    
    // Keep highlight when modal closes
    $(document).on('hidden.bs.modal', '.modal', function() {
        modalJustClosed = true;
        
        if (activeInvoiceId) {
            setTimeout(function() {
                if (modalJustClosed && activeInvoiceId) {
                    highlightRow(activeInvoiceId, false);
                }
            }, 100);
        }
        
        $(this).find('.modal-title .invoice-badge').remove();
    });
    
    // Handle form submissions
    $(document).on('submit', 'form', function() {
        if (activeInvoiceId) {
            sessionStorage.setItem('lastActionInvoiceId', activeInvoiceId);
        }
    });
    
    // ============================================
    // KEYBOARD & CLICK OUTSIDE HANDLERS
    // ============================================
    
    // ESC key to clear
    $(document).keydown(function(e) {
        if (e.key === 'Escape') {
            // Let an open SweetAlert or Bootstrap modal consume ESC first; don't
            // clear the "Working on Invoice" indicator while a dialog is closing.
            if (document.querySelector('.swal2-container') || $('.modal.show').length) {
                return;
            }
            clearActiveRow();
        }
    });

    // Click outside to clear
    var outsideClickTimer;
    $(document).on('click', function(e) {
        if ($(e.target).closest('table.datatable').length ||
            $(e.target).closest('.modal').length ||
            $(e.target).closest('.swal2-container').length ||
            $(e.target).closest('#workingOnIndicator').length) {
            return;
        }

        if (modalJustClosed) {
            modalJustClosed = false;
            return;
        }

        clearTimeout(outsideClickTimer);
        outsideClickTimer = setTimeout(function() {
            // A SweetAlert may have opened between the click and this timeout
            // (e.g. Add Payment / Send Reminder). Keep the indicator in that case.
            if (activeInvoiceId &&
                !$(e.target).closest('.modal').length &&
                !$(e.target).closest('.swal2-container').length &&
                !document.querySelector('.swal2-container')) {
                clearActiveRow();
            }
        }, 200);
    });
    
    // ============================================
    // RESTORE ON PAGE LOAD
    // ============================================
    
    function restoreActiveRow() {
        var storedId = sessionStorage.getItem('activeInvoiceId');
        var lastActionId = sessionStorage.getItem('lastActionInvoiceId');
        var storedTime = sessionStorage.getItem('activeInvoiceTime');
        var storedLabel = sessionStorage.getItem('activeInvoiceAction') || '';

        var targetId = lastActionId || storedId;

        if (targetId) {
            var isRecent = true;
            if (storedTime) {
                isRecent = (Date.now() - parseInt(storedTime)) < (30 * 60 * 1000);
            }

            if (isRecent) {
                setTimeout(function() {
                    if (highlightRow(targetId, true, storedLabel)) {
                        sessionStorage.removeItem('lastActionInvoiceId');
                    }
                }, 600);
            } else {
                sessionStorage.removeItem('activeInvoiceId');
                sessionStorage.removeItem('activeInvoiceTime');
                sessionStorage.removeItem('activeInvoiceAction');
            }
        }
    }
    
    restoreActiveRow();
    
    // Handle DataTables redraw
    if ($.fn.DataTable) {
        $(document).on('draw.dt', function() {
            var storedId = sessionStorage.getItem('activeInvoiceId');
            var storedLabel = sessionStorage.getItem('activeInvoiceAction') || '';
            if (storedId) {
                highlightRow(storedId, false, storedLabel);
            }
        });
    }
    
});
</script> 