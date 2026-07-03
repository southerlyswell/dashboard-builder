function projectsPage() {
    return {
        projects: [],
        filtered: [],
        search: '',
        clientFilter: '',
        loading: true,
        copiedId: null,

        init() {
            var self = this;
            fetch('/dashboard-builder/projects', {
                headers: { 'Accept': 'application/json' }
            }).then(function(r) {
                return r.json();
            }).then(function(data) {
                self.projects = data.map(function(d) {
                    d.icon = self.getIcon(d);
                    return d;
                });
                self.filtered = self.projects;
                self.loading = false;
            }).catch(function(e) {
                console.error('Projects load failed:', e);
                self.loading = false;
            });
        },

        getIcon(project) {
            var layout = project.layout || {};
            var cards = layout.cards || [];
            if (cards.some(function(c) { return c.type === 'line'; })) return '📈';
            if (cards.some(function(c) { return c.type === 'donut'; })) return '🍩';
            if (cards.some(function(c) { return c.type === 'table'; })) return '📋';
            return '📊';
        },

        filter() {
            var self = this;
            this.filtered = this.projects.filter(function(p) {
                var matchSearch = !self.search || p.name.toLowerCase().indexOf(self.search.toLowerCase()) >= 0;
                var matchClient = !self.clientFilter || p.client_id == self.clientFilter;
                return matchSearch && matchClient;
            });
        },

        loadProject(id) {
            window.location.href = '/dashboard-builder?project=' + id;
        },

        deleteProject(id) {
            if (!confirm('Delete this dashboard? This cannot be undone.')) return;
            var self = this;
            fetch('/dashboard-builder/projects/' + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                }
            }).then(function(r) {
                return r.json();
            }).then(function(data) {
                self.projects = self.projects.filter(function(p) { return p.id !== id; });
                self.filter();
            }).catch(function(e) {
                console.error('Delete failed:', e);
            });
        },

        copyEmbed(publicId) {
            var url = window.location.origin + '/embed/' + publicId;
            var self = this;
            navigator.clipboard.writeText(url).then(function() {
                self.copiedId = publicId;
                setTimeout(function() { self.copiedId = null; }, 2000);
            });
        }
    };
}
