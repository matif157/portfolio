<?php
// ============================================================================
// SECTION 1: DEFINING THE CLASS (THE BLUEPRINT)
// ============================================================================

// SYNTAX: class
// DEFINITION: "class" is a keyword used to create a custom blueprint or template for objects.
// WHY USE IT: Instead of scattered variables and functions, a class groups related data and code together.
// ALTERNATIVE: Procedural functions with global arrays. But classes are much cleaner and harder to break.
// WHAT: Creates a structure named LeadContactLog.
class LeadContactLog 
{
    // ------------------------------------------------------------------------
    // PROPERTY DEFINITION
    // ------------------------------------------------------------------------

    // SYNTAX: private
    // DEFINITION: "private" is a visibility modifier (access control).
    // WHY USE IT: It HIDES this variable from external code. This IS Encapsulation!
    // WHAT HAPPENS IF PUBLIC: Anyone outside could accidentally run $myLog->interactions = [] and erase all data.
    // ALTERNATIVES: "public" (open to everyone), "protected" (open to parent and child classes only).
    
    // SYNTAX: $
    // DEFINITION: In PHP, all variable names MUST start with a dollar sign ($).
    
    // SYNTAX: $interactions = array();
    // DEFINITION: Creates an empty array property to hold our list of interaction notes.
    // ALTERNATIVE: $interactions = []; (Short array syntax, both do the exact same thing).
    public $interactions = array();

    // ------------------------------------------------------------------------
    // METHOD 1: addNote()
    // ------------------------------------------------------------------------

    // SYNTAX: public
    // DEFINITION: "public" means this function CAN be called from outside the class.
    
    // SYNTAX: function
    // DEFINITION: Keyword used to create a reusable block of code.
    
    // SYNTAX: addNote($type, $note)
    // DEFINITION: Function name with two parameters (inputs) in parentheses, separated by a comma.
    public function addNote($type, $note) 
    {
        // SYNTAX: if (...) { ... }
        // DEFINITION: A conditional statement (decision maker). Executes code inside {} ONLY if true.
        // WHAT: Checks if $note is equal (==) to an empty string "".
        // WHY: Prevents users from submitting blank log entries.
        if ($note == "") {
            // SYNTAX: return
            // DEFINITION: Stops the function immediately and sends a value back to whoever called it.
            return "Error: Note text cannot be empty!";
        }

        // SYNTAX: array(...)
        // DEFINITION: Creates a key-value associative array to hold a single record's details.
        // WHAT: Packs 'type', 'note', and 'time' into one grouped packet.
        $newEntry = array(
            'type' => $type, // 'type' is the key, $type is the value from the user
            'note' => $note, // 'note' is the key, $note is the value from the user
            
            // SYNTAX: date('H:i:s')
            // DEFINITION: A built-in PHP function that gets current server time.
            // WHAT: 'H' = Hour (24h format), 'i' = Minute, 's' = Second.
            'time' => date('H:i:s') 
        );

        // SYNTAX: $this
        // DEFINITION: A special variable in PHP that refers to "THIS specific object instance right now".
        // WHY: Because inside a class, PHP doesn't know which variable name was used outside.
        
        // SYNTAX: -> (Object Operator / Arrow Operator)
        // DEFINITION: Accesses a property or method inside an object.
        // WHAT: $this->interactions means "the $interactions array belonging to THIS object".
        
        // SYNTAX: [] (Array Append Operator)
        // DEFINITION: Pushes a new item onto the END of an array.
        // ALTERNATIVE: array_push($this->interactions, $newEntry); (Both do the exact same thing).
        $this->interactions[] = $newEntry;

        return "Success: Note added!";
    }

    // ------------------------------------------------------------------------
    // METHOD 2: getNoteCount()
    // ------------------------------------------------------------------------

    public function getNoteCount() 
    {
        // SYNTAX: count(...)
        // DEFINITION: A built-in PHP function that counts elements in an array.
        // WHAT: Reads the private array $this->interactions and returns a single number (integer).
        return count($this->interactions);
    }

    // ------------------------------------------------------------------------
    // METHOD 3: getLatestNote()
    // ------------------------------------------------------------------------

    public function getLatestNote() 
    {
        // SYNTAX: $this->getNoteCount()
        // DEFINITION: Calling one method from INSIDE another method within the same class!
        // WHAT: Checks if total notes equals 0.
        if ($this->getNoteCount() == 0) {
            return "";
        }

        // SYNTAX: $lastIndex = $this->getNoteCount() - 1;
        // DEFINITION: Calculates the index position of the newest note in the array.
        // WHY subtract 1? Array index numbering ALWAYS starts at 0!
        // EXAMPLE: If array has 3 items, positions are 0, 1, and 2. So 3 - 1 = index 2.
        $lastIndex = $this->getNoteCount() - 1;

        // SYNTAX: $this->interactions[$lastIndex]
        // DEFINITION: Fetches the array item at position $lastIndex and returns it.
        return $this->interactions[$lastIndex];
    }
}

// ============================================================================
// SECTION 2: WORKFLOW CONTROLLER (USING THE CLASS)
// ============================================================================

// SYNTAX: new
// DEFINITION: The keyword used to instantiate (create) a real object from a class blueprint.
// WHAT: Takes the LeadContactLog blueprint and builds a live working object stored in $myLog.
$myLog = new LeadContactLog();

// SYNTAX: $statusMessage = "";
// DEFINITION: Pre-declares a string variable with empty text to hold feedback messages for the UI.
$statusMessage = "";

// SYNTAX: isset(...)
// DEFINITION: Built-in PHP function that checks "Does this variable exist and is it NOT null?"
// SYNTAX: $_POST
// DEFINITION: A built-in PHP Superglobal Array containing all data submitted from an HTML <form> using method="POST".
// WHAT: Checks if the user clicked the HTML submit button named "submit_note".
if (isset($_POST['submit_note'])) {
    
    // SYNTAX: $_POST['type']
    // DEFINITION: Extracts the value chosen in the HTML <select name="type"> dropdown.
    $userType = $_POST['type'];
    
    // SYNTAX: $_POST['note']
    // DEFINITION: Extracts the text typed into the HTML <input name="note"> field.
    $userNote = $_POST['note'];

    // WORKFLOW EXECUTION: Calling addNote() on our object instance.
    // WHAT: Calls addNote() on $myLog, passes the inputs, and catches the return string in $statusMessage.
    $statusMessage = $myLog->addNote($userType, $userNote);
}

// WORKFLOW EXECUTION: Fetching current state using public class getters.
$totalNotes = $myLog->getNoteCount();
$latestNote = $myLog->getLatestNote();

// ============================================================================
// SECTION 3: HTML VIEW (DISPLAYING DATA TO THE USER)
// ============================================================================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Beginner Encapsulation CRM</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
              align-items: center;
                padding:baseline;
                padding-bottom: 200px;
        }

        h3 {
            color: brown;
        }

        strong {
            color: green;
        }

        button[type="submit"] {
            color: darkblue;
            padding: 20px;
            font-size: large;
            background-color: lightgreen;
        }
         label  {
             padding: 10px;
            font-size: x-large;
            box-shadow: lightgreen;
        }
        type {
            box-sizing: border-box;
            box-shadow: inset color(srgb-linear red green blue);
            background-color: pink;
            from {
                align-items: center;
                padding:baseline;

            }
        }
        
   
    </style>
 
    <h1>CRM Lead Contact Log</h1>

    <!-- -------------------------------------------------------------------- -->
    <!-- STATUS ALERT MESSAGE DISPLAY                                         -->
    <!-- -------------------------------------------------------------------- -->
    
    <!-- SYNTAX: <?php if ($statusMessage != ""): ?> ... <?php endif; ?>      -->
    <!-- DEFINITION: Alternative PHP syntax for mixing conditional blocks inside HTML cleaner. -->
    <?php if ($statusMessage != ""): ?>
        <p style="color: blue;">
            <!-- SYNTAX: echo -->
            <!-- DEFINITION: The PHP command to print text/HTML directly onto the web page. -->
            <strong>System Status:</strong> <?php echo $statusMessage; ?>
        </p>
    <?php endif; ?>

    <hr>

    <!-- -------------------------------------------------------------------- -->
    <!-- FORM FOR TRIGGERING addNote()                                        -->
    <!-- -------------------------------------------------------------------- -->
    <h3>1. Add Note (Triggers <code>addNote()</code>)</h3>
    
    <!-- METHOD="POST": Sends form data invisibly inside HTTP headers to server -->
    <!-- ACTION="beginner_crm.php": Sends form data back to this exact same file -->
    <form method="POST" action="project.php">
        
        <label>Select Channel Type:</label><br>
        <select name="type">
            <option value="Call">Call</option>
            <option value="Email">Email</option>
            <option value="Meeting">Meeting</option>
        </select>
        <br><br>

        <label>Enter Interaction Details:</label><br>
        <input type="text" name="note" style="width: 300px;" required>
        <br><br>

        <!-- SUBMIT BUTTON: When clicked, $_POST['submit_note'] gets sent to PHP -->
        <button type="submit" name="submit_note"><b>Run addNote()</b></button>
    </form>

    <hr>

    <!-- -------------------------------------------------------------------- -->
    <!-- DISPLAYING OUTPUT FROM getNoteCount()                                -->
    <!-- -------------------------------------------------------------------- -->
    <h3>2. Total Items (Triggers <code>getNoteCount()</code>)</h3>
    <p>
        Total Notes Stored in Memory: 
        <strong><?php echo $totalNotes; ?></strong>
    </p>

    <hr>

    <!-- -------------------------------------------------------------------- -->
    <!-- DISPLAYING OUTPUT FROM getLatestNote()                               -->
    <!-- -------------------------------------------------------------------- -->
    <h3>3. Most Recent Item (Triggers <code>getLatestNote()</code>)</h3>
    
    <?php if ($latestNote != null): ?>
        <!-- Extracting array keys from the returned $latestNote record -->
        <p><strong>Channel:</strong> <?php echo $latestNote['type']; ?></p>
        <p><strong>Content:</strong> <?php echo $latestNote['note']; ?></p>
        <p><strong>Recorded Time:</strong> <?php echo $latestNote['time']; ?></p>
    <?php else: ?>
        <p><em>No notes exist yet. Method safely returned null.</em></p>
    <?php endif; ?>

</body>
</html>
<STYLE>
h3{
    color-scheme: brown;
}
strong {
    color-scheme: green;
    }
submit {
    color: darkblue; 
    padding: 20px;
}

</STYLE>


