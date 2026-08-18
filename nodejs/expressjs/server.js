const express = require('express');
const cors = require('cors');
const app = express();
const PORT = 3000;

app.use(cors());
app.use(express.json());

const state = {
    allItems: [],
    selectedIds: new Set(),
    sortedIds: [],
    idCounter: 1000000
};

function initializeData() {
    state.allItems = [];
    for (let i = 1; i <= state.idCounter; i++) {
        state.allItems.push({ id: i });
    }
    state.sortedIds = state.allItems.map(item => item.id);
}

initializeData();

class RequestQueue {
    constructor() {
        this.queue = [];
        this.processing = false;
        this.lastBatchTime = Date.now();
    }

    // Проверяем, есть ли уже такой запрос в очереди
    isDuplicate(action, payload) {
        return this.queue.some(item =>
            item.action === action &&
            JSON.stringify(item.payload) === JSON.stringify(payload)
        );
    }

    add(action, payload) {
        if (this.isDuplicate(action, payload)) {
            return;
        }

        this.queue.push({ action, payload, timestamp: Date.now() });
        this.processQueue();
    }

    async processQueue() {
        if (this.processing) return;

        this.processing = true;

        // Собираем запросы за последнюю секунду
        while (this.queue.length > 0) {
            const now = Date.now();
            const batch = this.queue.filter(item =>
                now - item.timestamp < 1000
            );

            if (batch.length === 0) {
                await this.sleep(100);
                continue;
            }

            await this.processBatch(batch);

            batch.forEach(item => {
                const index = this.queue.indexOf(item);
                if (index !== -1) {
                    this.queue.splice(index, 1);
                }
            });

            if (this.queue.length > 0) {
                await this.sleep(1000);
            }
        }

        this.processing = false;
    }

    async processBatch(batch) {
        // Группируем запросы по типу
        const actions = batch.reduce((acc, item) => {
            if (!acc[item.action]) {
                acc[item.action] = [];
            }
            acc[item.action].push(item.payload);
            return acc;
        }, {});

        // Обрабатываем каждый тип действий
        for (const [action, payloads] of Object.entries(actions)) {
            try {
                switch (action) {
                    case 'SELECT':
                        await this.handleSelect(payloads);
                        break;
                    case 'DESELECT':
                        await this.handleDeselect(payloads);
                        break;
                    case 'REORDER':
                        await this.handleReorder(payloads);
                        break;
                    case 'ADD_ITEM':
                        await this.handleAddItem(payloads);
                        break;
                }
            } catch (error) {
                console.error(`Error processing ${action}:`, error);
            }
        }
    }

    async handleSelect(payloads) {
        payloads.forEach(({ id }) => {
            state.selectedIds.add(id);
        });
    }

    async handleDeselect(payloads) {
        payloads.forEach(({ id }) => {
            state.selectedIds.delete(id);
        });
    }

    async handleReorder(payloads) {
        // Применяем последнюю операцию переупорядочивания
        const lastReorder = payloads[payloads.length - 1];
        if (lastReorder && lastReorder.ids) {
            state.sortedIds = lastReorder.ids;
        }
    }

    async handleAddItem(payloads) {
        payloads.forEach(({ id }) => {
            // Проверяем уникальность ID
            if (!state.allItems.some(item => item.id === id) && !state.selectedIds.has(id)) {
                state.allItems.push({ id });
                state.sortedIds.push(id);
                state.idCounter = Math.max(state.idCounter, id);
            }
        });
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

const requestQueue = new RequestQueue();

function paginateItems(items, page, limit = 20) {
    const start = page * limit;
    const end = start + limit;
    return items.slice(start, end);
}

app.get('/', (req, res) => {
    res.json({
        endpoints: {
            items: '/api/items',
            select: '/api/select (POST)',
            deselect: '/api/deselect (POST)',
            addItem: '/api/add-item (POST)',
            reorder: '/api/reorder (POST)',
        }
    });
});

app.get('/api/items', (req, res) => {
    const { page = 0, search = '', window = 'left' } = req.query;
    const pageNum = parseInt(page);
    const searchQuery = search.toLowerCase();

    let items;

    if (window === 'left') {
        // Левое окно: все элементы, кроме выбранных
        items = state.allItems
            .filter(item => !state.selectedIds.has(item.id))
            .map(item => item.id);
    } else {
        // Правое окно: выбранные элементы в порядке сортировки
        items = state.sortedIds
            .filter(id => state.selectedIds.has(id));
    }

    if (searchQuery) {
        items = items.filter(id =>
            id.toString().includes(searchQuery)
        );
    }

    const total = items.length;
    const paginatedItems = paginateItems(items, pageNum, 20);

    res.json({
        items: paginatedItems,
        total,
        page: pageNum,
        hasMore: (pageNum + 1) * 20 < total
    });
});
app.post('/api/select', (req, res) => {
    const { id } = req.body;
    if (id && !state.selectedIds.has(id)) {
        requestQueue.add('SELECT', { id });
    }
    res.json({ success: true });
});
app.post('/api/deselect', (req, res) => {
    const { id } = req.body;
    if (id && state.selectedIds.has(id)) {
        requestQueue.add('DESELECT', { id });
    }
    res.json({ success: true });
});
app.post('/api/add-item', (req, res) => {
    const { id } = req.body;
    const parsedId = parseInt(id);

    if (isNaN(parsedId) || parsedId < 1) {
        return res.status(400).json({ error: 'Invalid ID' });
    }

    // Проверяем, что ID не занят
    const exists = state.allItems.some(item => item.id === parsedId) ||
        state.selectedIds.has(parsedId);

    if (exists) {
        return res.status(400).json({ error: 'ID already exists' });
    }

    requestQueue.add('ADD_ITEM', { id: parsedId });
    res.json({ success: true });
});
app.post('/api/reorder', (req, res) => {
    const { ids } = req.body;
    if (Array.isArray(ids) && ids.length > 0) {
        const allSelected = ids.every(id => state.selectedIds.has(id));
        if (allSelected) {
            requestQueue.add('REORDER', { ids });
        }
    }
    res.json({ success: true });
});

app.listen(PORT, () => {
    console.log(PORT);
});