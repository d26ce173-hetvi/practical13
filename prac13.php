<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Practical 13 - AJAX Event Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f3f5f7;
        }

        .container {
            max-width: 950px;
            margin: auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
        }

        h2 {
            text-align: center;
        }

        input, button {
            padding: 10px;
            margin: 5px 0;
            box-sizing: border-box;
        }

        input {
            width: 100%;
        }

        button {
            cursor: pointer;
        }

        .message {
            margin: 12px 0;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .info {
            color: #444;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        th {
            background: #e9edf2;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Event Management System</h2>
    <p>PHP JSON API and AJAX/Fetch-Based CRUD</p>

    <div id="message" class="message info" role="status"
         aria-live="polite"></div>

    <form id="eventForm">
        <input type="hidden" name="event_id" id="event_id">

        <label for="event_name">Event Name:</label>
        <input type="text" name="event_name" id="event_name"
               maxlength="150" required>

        <label for="event_date">Event Date:</label>
        <input type="date" name="event_date" id="event_date" required>

        <label for="venue">Venue:</label>
        <input type="text" name="venue" id="venue"
               maxlength="150" required>

        <button type="submit" id="submitButton">Add Event</button>

        <button type="button" id="cancelEdit">
            Clear Form
        </button>
    </form>

    <h3>Saved Events</h3>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Event Name</th>
                <th>Event Date</th>
                <th>Venue</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody id="eventTable">
            <tr>
                <td colspan="5">Loading events...</td>
            </tr>
        </tbody>
    </table>

</div>

<script>
    const form = document.getElementById("eventForm");
    const eventTable = document.getElementById("eventTable");
    const messageBox = document.getElementById("message");
    const submitButton = document.getElementById("submitButton");
    const eventIdField = document.getElementById("event_id");

    let eventsData = [];

    // Display success, error and loading messages
    function showMessage(message, type = "info") {
        messageBox.textContent = message;
        messageBox.className = "message " + type;
    }

    // Escape text before placing database values inside HTML
    function escapeHTML(value) {
        const characters = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        };

        return String(value ?? "").replace(
            /[&<>"']/g,
            character => characters[character]
        );
    }

    // Send a request to the PHP JSON API
    async function apiRequest(action, formData = null) {
        const options = {
            method: formData ? "POST" : "GET"
        };

        if (formData) {
            options.headers = {
                "Content-Type":
                    "application/x-www-form-urlencoded"
            };

            options.body = new URLSearchParams(formData);
        }

        const response = await fetch(
            "api13.php?action=" + encodeURIComponent(action),
            options
        );

        let result;

        try {
            result = await response.json();
        } catch (error) {
            throw new Error("Server did not return valid JSON.");
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "The request failed."
            );
        }

        return result;
    }

    // Display database records in the table
    function renderEvents() {
        if (eventsData.length === 0) {
            eventTable.innerHTML =
                '<tr><td colspan="5">No events found.</td></tr>';
            return;
        }

        eventTable.innerHTML = eventsData.map(event => `
            <tr>
                <td>${escapeHTML(event.event_id)}</td>
                <td>${escapeHTML(event.event_name)}</td>
                <td>${escapeHTML(event.event_date)}</td>
                <td>${escapeHTML(event.venue)}</td>
                <td>
                    <button type="button"
                        data-edit="${Number(event.event_id)}">
                        Edit
                    </button>

                    <button type="button"
                        data-delete="${Number(event.event_id)}">
                        Delete
                    </button>
                </td>
            </tr>
        `).join("");
    }

    // READ: Fetch events without reloading the page
    async function loadEvents(showLoading = true) {
        if (showLoading) {
            showMessage("Loading events...", "info");
        }

        try {
            const result = await apiRequest("list");

            eventsData = result.data || [];
            renderEvents();

            if (showLoading) {
                showMessage("Events loaded successfully.", "success");
            }
        } catch (error) {
            showMessage(error.message, "error");
        }
    }

    // CREATE and UPDATE: Submit the form asynchronously
    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        const action = eventIdField.value ? "update" : "create";
        const formData = new FormData(form);

        submitButton.disabled = true;

        showMessage("Saving event...", "info");

        try {
            const result = await apiRequest(action, formData);

            resetForm();
            await loadEvents(false);

            showMessage(result.message, "success");
        } catch (error) {
            showMessage(error.message, "error");
        } finally {
            submitButton.disabled = false;
        }
    });

    // Handle Edit and Delete buttons
    eventTable.addEventListener("click", async function (event) {
        const editButton = event.target.closest("[data-edit]");
        const deleteButton = event.target.closest("[data-delete]");

        // Fill the form for editing
        if (editButton) {
            const id = editButton.dataset.edit;

            const selectedEvent = eventsData.find(
                item => String(item.event_id) === id
            );

            if (!selectedEvent) {
                showMessage("Event not found.", "error");
                return;
            }

            eventIdField.value = selectedEvent.event_id;
            document.getElementById("event_name").value =
                selectedEvent.event_name;
            document.getElementById("event_date").value =
                selectedEvent.event_date;
            document.getElementById("venue").value =
                selectedEvent.venue;

            submitButton.textContent = "Update Event";

            showMessage("Edit the event and click Update Event.", "info");
        }

        // DELETE: Ask for confirmation before deleting
        if (deleteButton) {
            const id = deleteButton.dataset.delete;

            if (!confirm("Are you sure you want to delete this event?")) {
                return;
            }

            const data = new FormData();
            data.append("event_id", id);

            showMessage("Deleting event...", "info");

            try {
                const result = await apiRequest("delete", data);

                await loadEvents(false);

                showMessage(result.message, "success");
            } catch (error) {
                showMessage(error.message, "error");
            }
        }
    });

    // Clear form and cancel editing
    function resetForm() {
        form.reset();
        eventIdField.value = "";
        submitButton.textContent = "Add Event";
    }

    document.getElementById("cancelEdit")
        .addEventListener("click", resetForm);

    // Load events when the page first opens
    loadEvents();
</script>

</body>
</html>

