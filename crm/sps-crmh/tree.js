/**
 * tree.js
 * ES6 Module: Interactive 360° Visual Relationship Tree
 * Renders a hierarchical, clickable SVG/CSS node tree of all CRM relationships
 */

class RelationshipTree {
    constructor(containerId, data) {
        this.container = document.getElementById(containerId);
        this.data = data;
        this.svgNS = 'http://www.w3.org/2000/svg';
        this.nodes = [];
        this.init();
    }

    init() {
        this.buildTree();
    }

    // ─── Build CSS flex-tree (works in all browsers without D3) ─────────────
    buildTree() {
        const d = this.data;
        const client = d.client;
        const clientName = client.company_name || `${client.first_name} ${client.last_name}`;

        // Root wrapper
        const root = document.createElement('div');
        root.className = 'tree-root';
        root.style.cssText = `
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: inherit;
            padding: 10px;
            min-width: 900px;
        `;

        // Inject tree CSS
        if (!document.getElementById('tree-css')) {
            const style = document.createElement('style');
            style.id = 'tree-css';
            style.textContent = `
                .tree-node-row { display:flex; justify-content:center; gap:18px; position:relative; margin:0 10px; }
                .tree-node {
                    position: relative;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    cursor: pointer;
                }
                .tree-node-box {
                    padding: 10px 16px;
                    border-radius: 10px;
                    border: 2px solid var(--border-color);
                    background: var(--bg-card);
                    box-shadow: var(--shadow-md);
                    font-size: 12px;
                    font-weight: 600;
                    text-align: center;
                    min-width: 110px;
                    max-width: 140px;
                    transition: transform 0.18s ease, box-shadow 0.18s ease;
                    word-break: break-word;
                    color: var(--text-dark);
                }
                .tree-node-box:hover {
                    transform: translateY(-4px) scale(1.05);
                    box-shadow: 0 12px 25px rgba(0,0,0,0.18);
                    border-color: var(--accent);
                    z-index: 10;
                }
                .tree-node-box.root-node {
                    background: var(--primary);
                    color: #fff;
                    border-color: var(--primary);
                    font-size: 14px;
                    min-width: 160px;
                    padding: 14px 20px;
                    border-radius: 14px;
                }
                .tree-node-icon { font-size: 18px; display: block; margin-bottom: 4px; }
                .tree-connector-v {
                    width: 2px;
                    background: var(--border-color);
                    height: 28px;
                    margin: 0 auto;
                }
                .tree-connector-h {
                    height: 2px;
                    background: var(--border-color);
                    position: absolute;
                    top: 0;
                    left: 50%;
                    right: 50%;
                }
                .tree-branch {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                }
                .tree-branch-row {
                    display: flex;
                    justify-content: center;
                    gap: 12px;
                    position: relative;
                    padding-top: 28px;
                }
                .tree-branch-row::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 2px;
                    height: 28px;
                    background: var(--border-color);
                }
                .tree-branch-row .tree-branch-col {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    position: relative;
                }
                .tree-badge {
                    display: inline-block;
                    padding: 1px 7px;
                    border-radius: 99px;
                    font-size: 10px;
                    font-weight: 700;
                    margin-top: 3px;
                }
                .tree-section-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: var(--text-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    margin-bottom: 8px;
                    text-align: center;
                }
                .tree-horizontal-bar {
                    position: absolute;
                    top: 0;
                    height: 2px;
                    background: var(--border-color);
                }
            `;
            document.head.appendChild(style);
        }

        // ROOT NODE: Client
        const rootNode = this.makeNode(
            '🏢', clientName, 'root-node',
            { strength: 'Root Entity', status: client.status },
            `client_profile.php?id=${client.id}`,
            `Client #${client.client_number} | Status: ${client.status} | Email: ${client.email}`
        );
        root.appendChild(rootNode);

        // CONNECTOR
        root.appendChild(this.connector('v'));

        // LEVEL 1: Horizontal row of category labels
        const level1 = document.createElement('div');
        level1.style.cssText = 'display:flex; gap:0; justify-content:center; width:100%;';

        const categories = [
            { icon: '🏬', label: 'Businesses', color: '#1e40af', items: d.businesses, urlFn: b => `businesses.php?client_id=${client.id}`, summaryFn: b => `${b.business_name} (${b.entity_type}) — Tax ID: ${b.tax_id || 'N/A'}`, badge: 'Entity', badgeColor: '#dbeafe' },
            { icon: '👤', label: 'Contacts', color: '#065f46', items: d.contacts, urlFn: c => `contacts.php?client_id=${client.id}`, summaryFn: c => `${c.name} — ${c.position || 'Contact'} | ${c.phone || c.email || 'N/A'}`, badge: 'Contact', badgeColor: '#d1fae5' },
            { icon: '⚙️', label: 'Services', color: '#4f46e5', items: d.services, urlFn: s => `service_workflow.php?service_id=${s.service_id}`, summaryFn: s => `${s.service_title} — $${parseFloat(s.price || 0).toFixed(2)} | Step ${s.current_step_number || 1}`, badge: 'Service', badgeColor: '#e0e7ff' },
            { icon: '📋', label: 'Projects', color: '#92400e', items: d.projects, urlFn: p => `project_view.php?id=${p.id}`, summaryFn: p => `${p.title} — ${p.status} | Due: ${p.due_date || 'N/A'}`, badge: 'Project', badgeColor: '#fef3c7' },
            { icon: '📄', label: 'Estimates', color: '#7c3aed', items: d.quotations, urlFn: q => `quotation_view.php?id=${q.id}`, summaryFn: q => `${q.quotation_number} — $${parseFloat(q.total_amount || 0).toFixed(2)} | ${q.status}`, badge: 'Quote', badgeColor: '#ede9fe' },
            { icon: '💰', label: 'Invoices', color: '#b91c1c', items: d.invoices, urlFn: i => `invoice_view.php?id=${i.id}`, summaryFn: i => `${i.invoice_number} — $${parseFloat(i.total_amount || 0).toFixed(2)} | ${i.status}`, badge: 'Invoice', badgeColor: '#fee2e2' },
            { icon: '🏦', label: 'Bank Accounts', color: '#0e7490', items: d.bank_accounts, urlFn: a => `bank_statements.php?business_id=${a.business_id}`, summaryFn: a => `${a.bank_name} x-${a.account_number_last4} (${a.account_type})`, badge: 'Bank', badgeColor: '#cffafe' },
        ];

        const sectionWrap = document.createElement('div');
        sectionWrap.style.cssText = 'display:flex; gap:16px; justify-content:center; flex-wrap:wrap; width:100%; margin-top:0;';

        categories.forEach(cat => {
            const col = document.createElement('div');
            col.style.cssText = 'display:flex; flex-direction:column; align-items:center; min-width:130px;';

            // Section header node
            const header = document.createElement('div');
            header.style.cssText = `background:${cat.color}18; border:2px solid ${cat.color}50; border-radius:10px; padding:8px 12px; text-align:center; font-size:12px; font-weight:700; color:${cat.color}; cursor:default; margin-bottom:10px; min-width:110px;`;
            header.innerHTML = `<span style="font-size:18px;">${cat.icon}</span><br>${cat.label} <span style="background:${cat.color}; color:#fff; border-radius:99px; padding:1px 7px; font-size:10px;">${cat.items.length}</span>`;
            col.appendChild(header);

            // Vertical connector from header
            const vLine = document.createElement('div');
            vLine.style.cssText = `width:2px; height:${cat.items.length > 0 ? '20px' : '10px'}; background:${cat.color}50; margin:0 auto;`;
            col.appendChild(vLine);

            if (cat.items.length > 0) {
                // Children items
                cat.items.forEach((item, idx) => {
                    const nameProp = item.business_name || item.name || item.service_title || item.title || item.quotation_number || item.invoice_number || item.bank_name || 'Item';
                    const shortName = nameProp.length > 18 ? nameProp.substring(0, 18) + '…' : nameProp;
                    const summary = cat.summaryFn(item);
                    const url = cat.urlFn(item);

                    const lineV = document.createElement('div');
                    lineV.style.cssText = `width:2px; height:8px; background:${cat.color}40; margin:0 auto;`;
                    col.appendChild(lineV);

                    const leaf = document.createElement('div');
                    leaf.className = 'tree-node-box';
                    leaf.style.cssText = `border-color:${cat.color}40; min-width:115px; max-width:135px; background:var(--bg-card); margin-bottom:0;`;
                    leaf.innerHTML = `<span style="font-size:10px; font-weight:800; color:${cat.color}; background:${cat.badgeColor}; padding:1px 6px; border-radius:99px; display:inline-block; margin-bottom:4px;">${cat.badge}</span><br><strong style="font-size:12px; color:var(--text-dark);">${this.escape(shortName)}</strong>`;

                    leaf.addEventListener('click', () => this.showNodeModal(nameProp, summary, url));
                    leaf.addEventListener('mouseenter', () => leaf.style.borderColor = cat.color);
                    leaf.addEventListener('mouseleave', () => leaf.style.borderColor = `${cat.color}40`);

                    col.appendChild(leaf);
                });
            } else {
                const empty = document.createElement('div');
                empty.style.cssText = `font-size:11px; color:var(--text-muted); text-align:center; padding:8px; border:1px dashed var(--border-color); border-radius:8px; min-width:110px;`;
                empty.textContent = 'None registered';
                col.appendChild(empty);
            }

            sectionWrap.appendChild(col);
        });

        root.appendChild(sectionWrap);

        // Relationship Strength Legend
        const legend = document.createElement('div');
        legend.style.cssText = 'margin-top:30px; padding:15px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:8px; display:flex; gap:20px; flex-wrap:wrap; font-size:12px;';
        legend.innerHTML = `
            <strong style="color:var(--secondary);">Relationship Strength:</strong>
            <span>🟢 <strong>Active Services</strong> — ${d.services.length} engagements</span>
            <span>📋 <strong>Open Projects</strong> — ${d.projects.filter(p => p.status === 'open').length} active</span>
            <span>📄 <strong>Outstanding Estimates</strong> — ${d.quotations.length} proposals</span>
            <span>💰 <strong>Unpaid Invoices</strong> — ${d.invoices.filter(i => i.status === 'unpaid' || i.status === 'overdue').length} pending</span>
        `;
        root.appendChild(legend);

        this.container.appendChild(root);
    }

    connector(type) {
        const el = document.createElement('div');
        el.style.cssText = type === 'v'
            ? 'width:2px; background:var(--border-color); height:28px; margin:0 auto;'
            : 'height:2px; background:var(--border-color); width:80%; margin:0 auto;';
        return el;
    }

    makeNode(icon, label, extraClass, meta, url, summary) {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex; flex-direction:column; align-items:center;';

        const box = document.createElement('div');
        box.className = `tree-node-box ${extraClass}`;
        box.innerHTML = `<span class="tree-node-icon">${icon}</span>${this.escape(label)}`;
        box.title = summary;
        box.addEventListener('click', () => this.showNodeModal(label, summary, url));

        wrap.appendChild(box);
        return wrap;
    }

    showNodeModal(title, summary, url) {
        document.getElementById('modalNodeTitle').textContent = title;
        document.getElementById('modalNodeBody').innerHTML = summary.replace(/\|/g, '<br>').replace(/—/g, '<br><span style="color:var(--text-muted);">');
        document.getElementById('modalNodeLink').href = url;
        document.getElementById('nodeDetailModal').style.display = 'block';
    }

    escape(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
}

// Boot on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    if (window.treeData) {
        new RelationshipTree('treeContainer', window.treeData);
    }
});
