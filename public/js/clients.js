function clientsPage() {
    return {
        clients: [],
        loading: true,
        showAddClient: false,
        form: {
            name: '', contact_name: '', contact_email: '', contact_phone: '',
            address_line1: '', city: '', province: '', postal_code: '', industry: '',
            db_host: '127.0.0.1', db_port: 3306, db_database: '', db_username: '', db_password: ''
        },

        init() {
            this.loadClients();
        },

        loadClients() {
            var self = this;
            fetch('/dashboard-builder/clients', {
                headers: { 'Accept': 'application/json' }
            }).then(function(r) { return r.json(); })
              .then(function(data) {
                  self.clients = data;
                  self.loading = false;
              }).catch(function(e) {
                  console.error('Load failed:', e);
                  self.loading = false;
              });
        },

        saveClient() {
            var self = this;
            var token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/dashboard-builder/clients', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify(this.form)
            }).then(function(r) { return r.json(); })
              .then(function(data) {
                  if (data.id) {
                      self.showAddClient = false;
                      self.form = { name: '', contact_name: '', contact_email: '', contact_phone: '', address_line1: '', city: '', province: '', postal_code: '', industry: '', db_host: '127.0.0.1', db_port: 3306, db_database: '', db_username: '', db_password: '' };
                      self.loadClients();
                  } else if (data.errors) {
                      alert('Validation error: ' + Object.values(data.errors).flat().join(', '));
                  }
              }).catch(function(e) {
                  console.error('Save failed:', e);
                  alert('Failed to save client. Check console.');
              });
        },

        deleteClient(id) {
            if (!confirm('Delete this client and ALL their dashboards? This cannot be undone.')) return;
            var self = this;
            var token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/dashboard-builder/clients/' + id, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                }
            }).then(function(r) { return r.json(); })
              .then(function() { self.loadClients(); })
              .catch(function(e) { console.error('Delete failed:', e); });
        },

        sendToClient(client) {
            if (client && client.contact_email) {
                var subject = 'Your Dashboard is Ready';
                var body = 'Hi ' + (client.contact_name || 'there') + ',\n\nYour dashboard has been prepared. You can view it here:\n' + window.location.origin + '/dashboard-builder/clients/' + client.id + '\n\nBest regards,\nACFS Dashboard Team';
                window.open('mailto:' + client.contact_email + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body), '_blank');
            } else {
                alert('No email address on file for this client.');
            }
        }
    };
}
