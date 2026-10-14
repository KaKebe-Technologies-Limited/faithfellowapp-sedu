<?php

namespace App\Support;

use App\Models\Quiz;
use App\Models\QuizQuestion;

/**
 * The ready-made community games: 25 Bible and faith quizzes of ten questions each that anyone can pick, start and share.
 * They belong to the platform (no church), so every member sees them. Each question is written as
 * [question, right answer, [three wrong answers]]; install() places the right answer in a steady but mixed-up position
 * (so it is not always "A") and can be run again and again without making copies.
 */
class SampleGames
{
    /** key => [label, colour used by the app] */
    public const CATEGORIES = [
        'old-testament' => ['Old Testament', '#8E3B1F'],
        'people' => ['People & wisdom', '#0F6E6E'],
        'jesus' => ['Jesus & the Gospels', '#BC2649'],
        'church' => ['Early church & letters', '#4527A0'],
        'explore' => ['Explore the Bible', '#1D5FD1'],
        'uganda' => ['Faith in Uganda', '#12704F'],
    ];

    /** Creates the games that are missing (matched by title) with their questions. Returns how many games were added. */
    public static function install(): int
    {
        $added = 0;

        foreach (self::all() as $game) {
            $quiz = Quiz::withTrashed()->where('is_sample', true)->where('title', $game['title'])->first();
            if (! $quiz) {
                $quiz = Quiz::create([
                    'church_id' => null, 'user_id' => null, 'title' => $game['title'], 'description' => $game['description'],
                    'category' => $game['category'], 'emoji' => $game['emoji'], 'is_sample' => true,
                ]);
                $added++;
            }
            if ($quiz->questions()->exists()) {
                continue;
            }

            foreach ($game['questions'] as $i => [$text, $right, $wrong]) {
                $slot = crc32($game['title'].'|'.$i) % 4;
                $options = $wrong;
                array_splice($options, $slot, 0, [$right]);

                QuizQuestion::create([
                    'quiz_id' => $quiz->id, 'position' => $i, 'question' => $text, 'options' => $options, 'correct_option' => $slot,
                    'time_limit' => 10, 'points' => 1000,
                ]);
            }
        }

        return $added;
    }

    /** @return array<int, array{title: string, category: string, emoji: string, description: string, questions: array<int, array{0: string, 1: string, 2: array<int, string>}>}> */
    public static function all(): array
    {
        return [...self::oldTestament(), ...self::people(), ...self::jesus(), ...self::church(), ...self::explore(), ...self::uganda()];
    }

    protected static function game(string $title, string $category, string $emoji, string $description, array $questions): array
    {
        return compact('title', 'category', 'emoji', 'description', 'questions');
    }

    protected static function oldTestament(): array
    {
        return [
            self::game('Creation & Genesis', 'old-testament', '🌍', 'The first chapters of the Bible: creation, the garden and the first family.', [
                ['On which day of creation did God make the sun, moon and stars?', 'The fourth day', ['The second day', 'The third day', 'The sixth day']],
                ['On the sixth day God made the land animals and…', 'Man and woman', ['The birds of the air', 'The great sea creatures', 'The sun and the moon']],
                ['What was Adam made from?', 'The dust of the ground', ['Clay from the river', 'Water and fire', 'The breath of an angel']],
                ['God made Eve from what?', 'One of Adam’s ribs', ['A handful of dust', 'The tree of life', 'A cloud of mist']],
                ['What was the name of the garden where Adam and Eve lived?', 'Eden', ['Gethsemane', 'Carmel', 'Moriah']],
                ['Who tempted Eve to eat the forbidden fruit?', 'The serpent', ['A lion', 'A raven', 'A dragon']],
                ['Cain and Abel were the sons of…', 'Adam and Eve', ['Noah and his wife', 'Abraham and Sarah', 'Isaac and Rebekah']],
                ['Who was the first man to be born, according to Genesis?', 'Cain', ['Abel', 'Seth', 'Enoch']],
                ['Which man “walked with God, and he was not, for God took him”?', 'Enoch', ['Methuselah', 'Lamech', 'Seth']],
                ['What did God do on the seventh day?', 'He rested from his work', ['He created the animals', 'He made man', 'He separated the waters']],
            ]),
            self::game('Noah, Abraham & Family', 'old-testament', '🕊️', 'The ark, the promise to Abraham and the family God chose.', [
                ['How many people were saved in Noah’s ark?', 'Eight', ['Two', 'Four', 'Twelve']],
                ['Which bird returned to Noah with an olive leaf?', 'A dove', ['An eagle', 'A sparrow', 'A swallow']],
                ['What sign did God give of his promise never to flood the whole earth again?', 'The rainbow', ['A star', 'A pillar of cloud', 'A burning bush']],
                ['God changed Abram’s name to Abraham. What was Abraham to become?', 'The father of many nations', ['The king of Egypt', 'The ruler of Canaan', 'The first priest']],
                ['Who was Abraham’s wife, who laughed when told she would have a son?', 'Sarah', ['Rebekah', 'Rachel', 'Leah']],
                ['Who was Abraham’s son of promise?', 'Isaac', ['Ishmael', 'Jacob', 'Esau']],
                ['What did God provide in place of Isaac on the mountain?', 'A ram', ['A lamb', 'A goat', 'A bull']],
                ['Jacob and Esau were the twin sons of Isaac and…', 'Rebekah', ['Sarah', 'Leah', 'Rachel']],
                ['How many sons did Jacob have?', 'Twelve', ['Seven', 'Ten', 'Fourteen']],
                ['Which of Jacob’s sons was sold by his brothers into slavery?', 'Joseph', ['Benjamin', 'Judah', 'Reuben']],
            ]),
            self::game('Moses & the Exodus', 'old-testament', '🌊', 'From the burning bush to the edge of the Promised Land.', [
                ['On which mountain did Moses see the burning bush?', 'Horeb (Sinai)', ['Carmel', 'Nebo', 'Ararat']],
                ['Who was Moses’ brother and spokesman?', 'Aaron', ['Joshua', 'Caleb', 'Eleazar']],
                ['How many plagues struck Egypt?', 'Ten', ['Seven', 'Twelve', 'Forty']],
                ['What was the last plague on Egypt?', 'The death of every firstborn', ['Darkness', 'Hail', 'Locusts']],
                ['What was put on the doorposts so the destroyer would pass over?', 'The blood of a lamb', ['Oil', 'Water', 'Ashes']],
                ['Which sea did Israel cross on dry ground?', 'The Red Sea', ['The Dead Sea', 'The Sea of Galilee', 'The Mediterranean Sea']],
                ['What food did God send from heaven each morning in the wilderness?', 'Manna', ['Honey cakes', 'Figs', 'Barley bread']],
                ['On which mountain did Moses receive the Ten Commandments?', 'Mount Sinai', ['Mount Zion', 'Mount Carmel', 'Mount Nebo']],
                ['From which mountain did Moses see the Promised Land before he died?', 'Mount Nebo', ['Mount Sinai', 'Mount Hermon', 'Mount Tabor']],
                ['Who led Israel into the Promised Land after Moses?', 'Joshua', ['Caleb', 'Aaron', 'Samuel']],
            ]),
            self::game('Joshua & the Judges', 'old-testament', '🛡️', 'Walls that fell, heroes who rose and a people who needed leaders.', [
                ['Which city’s walls fell after Israel marched round it for seven days?', 'Jericho', ['Ai', 'Gaza', 'Hebron']],
                ['Which woman of Jericho hid the two spies?', 'Rahab', ['Deborah', 'Jael', 'Delilah']],
                ['What did Joshua command the sun to do at Gibeon?', 'Stand still', ['Rise early', 'Go backwards', 'Turn red']],
                ['Which woman judge led Israel from under a palm tree?', 'Deborah', ['Jael', 'Ruth', 'Hannah']],
                ['How many men did Gideon take to defeat the Midianites?', '300', ['3,000', '10,000', '32,000']],
                ['Which judge’s great strength was tied to his uncut hair?', 'Samson', ['Gideon', 'Jephthah', 'Ehud']],
                ['Which woman gave away the secret of Samson’s strength?', 'Delilah', ['Rahab', 'Jael', 'Orpah']],
                ['Which Moabite woman stayed with Naomi and became David’s great-grandmother?', 'Ruth', ['Orpah', 'Esther', 'Abigail']],
                ['Who was the prophet and last judge who anointed Israel’s first king?', 'Samuel', ['Eli', 'Nathan', 'Elijah']],
                ['Joshua declared: “As for me and my house, we will…”', 'Serve the Lord', ['Keep the land', 'Follow the king', 'Build the temple']],
            ]),
            self::game('David: Shepherd to King', 'old-testament', '👑', 'The boy from Bethlehem who killed a giant and became Israel’s greatest king.', [
                ['Whose son was David, from Bethlehem?', 'Jesse', ['Saul', 'Obed', 'Kish']],
                ['Which prophet anointed David as king?', 'Samuel', ['Nathan', 'Elijah', 'Gad']],
                ['What did David use to defeat Goliath?', 'A sling and a stone', ['A bow and arrows', 'Saul’s sword', 'A spear']],
                ['How many smooth stones did David pick up from the brook?', 'Five', ['One', 'Three', 'Seven']],
                ['Who was David’s close friend, King Saul’s son?', 'Jonathan', ['Abner', 'Joab', 'Absalom']],
                ['Which instrument did David play to calm King Saul?', 'The harp', ['The trumpet', 'The flute', 'The drum']],
                ['Which city did David make the capital of Israel?', 'Jerusalem', ['Hebron', 'Samaria', 'Shiloh']],
                ['Which prophet told David the story that exposed his sin with Bathsheba?', 'Nathan', ['Samuel', 'Elisha', 'Isaiah']],
                ['Which of David’s sons rebelled against him?', 'Absalom', ['Solomon', 'Jonathan', 'Joab']],
                ['Who became king after David?', 'Solomon', ['Absalom', 'Rehoboam', 'Saul']],
            ]),
            self::game('Kings of Israel & Judah', 'old-testament', '🏰', 'Wise kings, wicked kings and the fall of Jerusalem.', [
                ['Who was the first king of Israel?', 'Saul', ['David', 'Solomon', 'Samuel']],
                ['Which king asked God for wisdom and became famous for it?', 'Solomon', ['David', 'Hezekiah', 'Josiah']],
                ['Whose harsh words split Solomon’s kingdom in two?', 'Rehoboam', ['Jeroboam', 'Ahab', 'Asa']],
                ['Which king of Israel was married to Jezebel?', 'Ahab', ['Jehu', 'Omri', 'Jeroboam']],
                ['Which prophet challenged the prophets of Baal on Mount Carmel?', 'Elijah', ['Elisha', 'Isaiah', 'Jeremiah']],
                ['Which king of Judah prayed and was given fifteen more years of life?', 'Hezekiah', ['Josiah', 'Uzziah', 'Asa']],
                ['Which king began to reign at eight years old and found the Book of the Law?', 'Josiah', ['Joash', 'Uzziah', 'Manasseh']],
                ['Which empire destroyed Jerusalem and took Judah into exile?', 'Babylon', ['Assyria', 'Egypt', 'Persia']],
                ['Which king of Babylon destroyed Jerusalem?', 'Nebuchadnezzar', ['Belshazzar', 'Cyrus', 'Darius']],
                ['Which Persian king let the exiles go home to rebuild the temple?', 'Cyrus', ['Nebuchadnezzar', 'Belshazzar', 'Herod']],
            ]),
            self::game('The Prophets', 'old-testament', '📜', 'The voices God sent to his people.', [
                ['Which prophet was swallowed by a great fish?', 'Jonah', ['Amos', 'Hosea', 'Micah']],
                ['To which city was Jonah sent to preach?', 'Nineveh', ['Babylon', 'Tyre', 'Damascus']],
                ['Which prophet saw the Lord “high and lifted up” and answered, “Here I am! Send me”?', 'Isaiah', ['Jeremiah', 'Ezekiel', 'Joel']],
                ['Which prophet is known as the “weeping prophet”?', 'Jeremiah', ['Isaiah', 'Daniel', 'Hosea']],
                ['Which prophet saw a valley of dry bones come to life?', 'Ezekiel', ['Daniel', 'Isaiah', 'Zechariah']],
                ['Which prophet went up to heaven in a whirlwind with a chariot of fire?', 'Elijah', ['Elisha', 'Moses', 'Samuel']],
                ['Who received Elijah’s cloak and became the next prophet?', 'Elisha', ['Gehazi', 'Samuel', 'Jonah']],
                ['Which prophet foretold that the Messiah would come from Bethlehem?', 'Micah', ['Amos', 'Obadiah', 'Nahum']],
                ['Which prophet married Gomer as a picture of God’s love for an unfaithful people?', 'Hosea', ['Joel', 'Amos', 'Malachi']],
                ['Which is the last book of the Old Testament?', 'Malachi', ['Zechariah', 'Haggai', 'Nehemiah']],
            ]),
            self::game('Daniel & the Exiles', 'old-testament', '🦁', 'Faith in a foreign land: the furnace, the lions and a queen who saved her people.', [
                ['Shadrach, Meshach and Abednego were thrown into what for refusing to bow to the golden image?', 'A blazing furnace', ['A lions’ den', 'A deep pit', 'A river']],
                ['Who did King Nebuchadnezzar see walking with them in the fire?', 'A fourth figure like a son of the gods', ['Daniel', 'The prophet Ezekiel', 'His own son']],
                ['What did Daniel and his friends ask to eat instead of the king’s food?', 'Vegetables and water', ['Fish and bread', 'Lamb and wine', 'Honey and locusts']],
                ['Who explained Nebuchadnezzar’s dream of the great statue?', 'Daniel', ['Ezekiel', 'Joseph', 'Mordecai']],
                ['What happened at King Belshazzar’s feast?', 'A hand wrote on the wall', ['A star fell from the sky', 'The palace wall collapsed', 'A voice sang from the ceiling']],
                ['Where was Daniel thrown for praying to his God?', 'The lions’ den', ['A fiery furnace', 'A deep well', 'A prison tower']],
                ['How many times a day did Daniel pray?', 'Three times', ['Once', 'Twice', 'Seven times']],
                ['Which Jewish girl became queen of Persia and saved her people?', 'Esther', ['Ruth', 'Vashti', 'Naomi']],
                ['Who was Esther’s cousin who brought her up?', 'Mordecai', ['Haman', 'Ezra', 'Nehemiah']],
                ['Who led the rebuilding of Jerusalem’s walls?', 'Nehemiah', ['Ezra', 'Zerubbabel', 'Daniel']],
            ]),
        ];
    }

    protected static function people(): array
    {
        return [
            self::game('Psalms & Proverbs', 'people', '🎶', 'Songs of worship and sayings of wisdom.', [
                ['“The Lord is my shepherd; I shall not…”', 'Want', ['Fall', 'Weep', 'Wander']],
                ['Which is the longest chapter in the Bible?', 'Psalm 119', ['Psalm 23', 'Isaiah 53', 'Genesis 1']],
                ['Which is the shortest chapter in the Bible?', 'Psalm 117', ['Psalm 23', 'Psalm 1', 'Psalm 150']],
                ['Which king wrote most of the Psalms?', 'David', ['Solomon', 'Hezekiah', 'Moses']],
                ['Which psalm begins “Blessed is the man who walks not in the counsel of the wicked”?', 'Psalm 1', ['Psalm 23', 'Psalm 100', 'Psalm 150']],
                ['“Trust in the Lord with all your heart” is found in which book?', 'Proverbs', ['Psalms', 'Ecclesiastes', 'Isaiah']],
                ['Who wrote most of the book of Proverbs?', 'Solomon', ['David', 'Hezekiah', 'Moses']],
                ['“A soft answer turns away…” what?', 'Wrath', ['Hunger', 'Sin', 'Doubt']],
                ['Which psalm says, “Be still, and know that I am God”?', 'Psalm 46', ['Psalm 23', 'Psalm 91', 'Psalm 121']],
                ['“I lift up my eyes to the hills. From where does my help come?” is in…', 'Psalm 121', ['Psalm 23', 'Psalm 51', 'Psalm 150']],
            ]),
            self::game('Women of the Bible', 'people', '🌸', 'Mothers, queens, prophetesses and faithful friends.', [
                ['Who was the first woman?', 'Eve', ['Sarah', 'Hannah', 'Mary']],
                ['Who prayed at Shiloh for a child and became the mother of Samuel?', 'Hannah', ['Sarah', 'Rachel', 'Elizabeth']],
                ['Which prophetess and judge led Israel into battle with Barak?', 'Deborah', ['Huldah', 'Miriam', 'Esther']],
                ['Who was Moses’ sister, who watched over him in the river?', 'Miriam', ['Zipporah', 'Deborah', 'Rahab']],
                ['Who put two small coins into the temple treasury and was praised by Jesus?', 'A poor widow', ['A rich ruler’s wife', 'Martha', 'Lydia']],
                ['Which sister sat at Jesus’ feet to listen while Martha served?', 'Mary', ['Rhoda', 'Dorcas', 'Priscilla']],
                ['Who was the first person to see Jesus after he rose from the dead?', 'Mary Magdalene', ['Peter', 'Thomas', 'Salome']],
                ['Which woman of Joppa, known for her good works, was raised from the dead by Peter?', 'Tabitha (Dorcas)', ['Lydia', 'Priscilla', 'Eunice']],
                ['Who was Timothy’s grandmother?', 'Lois', ['Lydia', 'Anna', 'Phoebe']],
                ['Which seller of purple cloth in Philippi became Paul’s first convert in Europe?', 'Lydia', ['Priscilla', 'Phoebe', 'Rhoda']],
            ]),
        ];
    }

    protected static function jesus(): array
    {
        return [
            self::game('The Christmas Story', 'jesus', '⭐', 'The birth of Jesus, from Nazareth to Bethlehem and beyond.', [
                ['Which angel told Mary she would bear Jesus?', 'Gabriel', ['Michael', 'Raphael', 'Uriel']],
                ['In which town was Jesus born?', 'Bethlehem', ['Nazareth', 'Jerusalem', 'Capernaum']],
                ['In which town did Mary and Joseph live before the journey?', 'Nazareth', ['Bethlehem', 'Cana', 'Jericho']],
                ['Why did Joseph and Mary travel to Bethlehem?', 'For the census ordered by Caesar Augustus', ['To escape King Herod', 'To attend a wedding', 'To visit Elizabeth']],
                ['Where was baby Jesus laid after he was born?', 'In a manger', ['In a basket', 'In the temple', 'In a golden cradle']],
                ['Who were the first to hear the angels’ news of Jesus’ birth?', 'Shepherds', ['Wise men', 'Priests', 'Fishermen']],
                ['What led the wise men to Jesus?', 'A star', ['A cloud', 'A pillar of fire', 'An angel']],
                ['What gifts did the wise men bring?', 'Gold, frankincense and myrrh', ['Gold, silver and bronze', 'Wine, oil and bread', 'Silk, spices and pearls']],
                ['Which king tried to kill the child Jesus?', 'Herod', ['Caesar Augustus', 'Pilate', 'Nebuchadnezzar']],
                ['To which country did Joseph take Mary and Jesus to escape Herod?', 'Egypt', ['Syria', 'Moab', 'Greece']],
            ]),
            self::game('Parables of Jesus', 'jesus', '🌱', 'The stories Jesus told to teach about God’s kingdom.', [
                ['In the story of the Good Samaritan, who stopped to help the wounded man?', 'The Samaritan', ['A priest', 'A Levite', 'A Pharisee']],
                ['What job did the younger son take in the far country?', 'Feeding pigs', ['Tending sheep', 'Building houses', 'Fishing']],
                ['How did the father respond when the lost son came home?', 'He ran, embraced him and held a feast', ['He sent him away', 'He made him a servant', 'He ignored him']],
                ['In the parable of the sower, what happened to the seed on good soil?', 'It produced a great harvest', ['Birds ate it', 'It withered in the sun', 'Thorns choked it']],
                ['What did the wise man build his house upon?', 'Rock', ['Sand', 'Clay', 'Wood']],
                ['In the parable of the talents, what did the servant who feared his master do with his talent?', 'He buried it in the ground', ['He spent it', 'He gave it to the poor', 'He bought a field with it']],
                ['What was lost in the parable of the lost coin?', 'A silver coin', ['A gold ring', 'A sheep', 'A pearl']],
                ['In the parable of the ten virgins, what did the foolish ones forget to bring?', 'Oil for their lamps', ['Their lamps', 'Their bread', 'Their wedding robes']],
                ['The kingdom of heaven is compared to a mustard seed because it…', 'Starts tiny and grows into a large tree', ['Is hidden under a basket', 'Can fly like a bird', 'Flows like a river']],
                ['In the story of the Pharisee and the tax collector, who went home justified?', 'The tax collector', ['The Pharisee', 'Both of them', 'Neither of them']],
            ]),
            self::game('Miracles & Encounters of Jesus', 'jesus', '✨', 'Water into wine, storms stilled, the dead raised and lives changed.', [
                ['What was Jesus’ first miracle, at a wedding in Cana?', 'Turning water into wine', ['Healing a blind man', 'Feeding 5,000 people', 'Calming a storm']],
                ['How much food fed the 5,000 men?', 'Five loaves and two fish', ['Seven loaves and a few fish', 'Two loaves and five fish', 'Twelve loaves and three fish']],
                ['How many baskets of leftovers were gathered after the 5,000 were fed?', 'Twelve', ['Seven', 'Ten', 'Five']],
                ['What did Jesus do when the disciples were afraid in the storm on the lake?', 'He calmed the wind and the waves', ['He prayed all night', 'He sent them ashore', 'He walked away']],
                ['Which disciple stepped out of the boat to walk on water toward Jesus?', 'Peter', ['John', 'Andrew', 'Thomas']],
                ['Whom did Jesus raise after four days in the tomb?', 'Lazarus', ['Jairus’ daughter', 'Dorcas', 'The widow’s son']],
                ['How old was Jairus’ daughter when Jesus raised her?', 'Twelve', ['Seven', 'Ten', 'Sixteen']],
                ['Which tax collector climbed a sycamore tree to see Jesus?', 'Zacchaeus', ['Matthew', 'Bartimaeus', 'Nicodemus']],
                ['Which blind man at Jericho cried, “Son of David, have mercy on me”?', 'Bartimaeus', ['Zacchaeus', 'Lazarus', 'Jairus']],
                ['How many of the ten healed lepers came back to thank Jesus?', 'One', ['Two', 'Three', 'Nine']],
            ]),
            self::game('The Twelve Disciples', 'jesus', '🐟', 'The men Jesus called to follow him.', [
                ['Which disciple was Simon Peter’s brother?', 'Andrew', ['James', 'John', 'Philip']],
                ['Which two disciples were the sons of Zebedee?', 'James and John', ['Peter and Andrew', 'Philip and Bartholomew', 'Matthew and Thomas']],
                ['Which disciple had been a tax collector?', 'Matthew', ['Thomas', 'Philip', 'Simon']],
                ['Which disciple doubted until he saw the wounds of Jesus?', 'Thomas', ['Peter', 'Judas', 'Andrew']],
                ['Which disciple betrayed Jesus for thirty pieces of silver?', 'Judas Iscariot', ['Peter', 'Thomas', 'James']],
                ['Which disciple denied Jesus three times?', 'Peter', ['John', 'Judas', 'Andrew']],
                ['Who was chosen to take Judas’ place among the twelve?', 'Matthias', ['Barnabas', 'Silas', 'Timothy']],
                ['Which disciple is called “the one whom Jesus loved”?', 'John', ['Peter', 'Andrew', 'James']],
                ['What did Jesus say Simon and Andrew would become?', 'Fishers of men', ['Shepherds of the flock', 'Builders of the temple', 'Teachers of the law']],
                ['How many apostles did Jesus choose?', 'Twelve', ['Seven', 'Ten', 'Seventy']],
            ]),
            self::game('Easter Week', 'jesus', '✝️', 'From Palm Sunday to the empty tomb.', [
                ['How did Jesus enter Jerusalem on Palm Sunday?', 'Riding a donkey', ['On a horse', 'On a camel', 'On foot']],
                ['What did the crowd lay on the road before him?', 'Palm branches and cloaks', ['Silver coins', 'White flags', 'Burning torches']],
                ['What meal did Jesus share with his disciples the night before he died?', 'The Passover meal', ['A wedding feast', 'A harvest festival', 'A fish supper by the lake']],
                ['In which garden did Jesus pray before he was arrested?', 'Gethsemane', ['Eden', 'Hermon', 'Bethany']],
                ['Which Roman governor sentenced Jesus to be crucified?', 'Pontius Pilate', ['Herod Antipas', 'Caesar Augustus', 'Caiaphas']],
                ['Which prisoner was released instead of Jesus?', 'Barabbas', ['Judas', 'Nicodemus', 'Lazarus']],
                ['What was the place of the crucifixion called?', 'Golgotha, the Place of the Skull', ['Gethsemane', 'Bethany', 'Moriah']],
                ['Who gave his own new tomb for the burial of Jesus?', 'Joseph of Arimathea', ['Nicodemus', 'Simon of Cyrene', 'Lazarus']],
                ['On which day of the week did Jesus rise from the dead?', 'Sunday, the first day of the week', ['Friday', 'Saturday, the Sabbath', 'Thursday']],
                ['Which two disciples ran to the tomb on Easter morning?', 'Peter and John', ['James and John', 'Peter and Andrew', 'Thomas and Philip']],
            ]),
            self::game('Sermon on the Mount', 'jesus', '⛰️', 'The most famous teaching of Jesus, in Matthew 5–7.', [
                ['Where did Jesus teach the Sermon on the Mount?', 'On a mountainside', ['In a boat', 'In the temple', 'In a synagogue']],
                ['“Blessed are the poor in spirit, for theirs is…”', 'The kingdom of heaven', ['The earth', 'Comfort', 'Mercy']],
                ['“Blessed are the meek, for they shall…”', 'Inherit the earth', ['See God', 'Be satisfied', 'Be comforted']],
                ['“Blessed are the pure in heart, for they shall…”', 'See God', ['Inherit the earth', 'Receive mercy', 'Be called sons of God']],
                ['“You are the salt of the…”', 'Earth', ['Sea', 'Field', 'Hills']],
                ['“You are the light of the…”', 'World', ['Mountain', 'Lamp', 'Temple']],
                ['Which prayer begins “Our Father in heaven”?', 'The Lord’s Prayer', ['The Shema', 'The Benediction', 'The Song of Moses']],
                ['“Do not worry about tomorrow, for tomorrow will…”', 'Worry about itself', ['Bring joy', 'Come soon', 'Take care of you']],
                ['“Ask, and it will be given to you; seek, and you will find; knock, and…”', 'It will be opened to you', ['The Lord will answer', 'The door is yours', 'You will enter']],
                ['“Lay up for yourselves treasures in…”', 'Heaven', ['The temple', 'The field', 'The bank']],
            ]),
        ];
    }

    protected static function church(): array
    {
        return [
            self::game('Acts of the Apostles', 'church', '🔥', 'The Spirit comes and the church grows.', [
                ['On which festival did the Holy Spirit come upon the believers?', 'Pentecost', ['Passover', 'Tabernacles', 'Purim']],
                ['What appeared above each believer at Pentecost?', 'Tongues of fire', ['A dove', 'A crown of light', 'A star']],
                ['Who preached on the day of Pentecost, when about 3,000 believed?', 'Peter', ['Paul', 'John', 'Stephen']],
                ['Who was the first Christian martyr?', 'Stephen', ['James', 'Peter', 'Barnabas']],
                ['Who stood by approving while Stephen was stoned?', 'Saul', ['Barnabas', 'Philip', 'Silas']],
                ['On which road did Saul meet the risen Jesus?', 'The road to Damascus', ['The road to Jericho', 'The road to Emmaus', 'The road to Rome']],
                ['Whom did Philip meet on the desert road to Gaza, reading the prophet Isaiah?', 'An Ethiopian official', ['A Roman centurion', 'A Pharisee', 'A Greek merchant']],
                ['Which Roman centurion became the first Gentile convert in Acts?', 'Cornelius', ['Julius', 'Sergius Paulus', 'Felix']],
                ['Which servant girl was so overjoyed to hear Peter at the gate that she forgot to open it?', 'Rhoda', ['Dorcas', 'Lydia', 'Priscilla']],
                ['In which city were the followers of Jesus first called Christians?', 'Antioch', ['Jerusalem', 'Damascus', 'Rome']],
            ]),
            self::game('Paul: Journeys & Letters', 'church', '✉️', 'The missionary who carried the gospel across the Roman world.', [
                ['What was Paul’s name before he met Jesus?', 'Saul', ['Simon', 'Silas', 'Stephen']],
                ['What was Paul’s trade?', 'Tentmaker', ['Fisherman', 'Carpenter', 'Tax collector']],
                ['Who travelled with Paul on his first missionary journey?', 'Barnabas', ['Silas', 'Timothy', 'Luke']],
                ['Who went with Paul on his second journey after he and Barnabas parted?', 'Silas', ['Barnabas', 'Peter', 'Titus']],
                ['In which city were Paul and Silas jailed until an earthquake opened the doors?', 'Philippi', ['Corinth', 'Athens', 'Ephesus']],
                ['In which city did Paul speak to the people about “the unknown god”?', 'Athens', ['Rome', 'Corinth', 'Antioch']],
                ['Which letter says, “For by grace you have been saved through faith”?', 'Ephesians', ['Romans', 'Galatians', 'Philippians']],
                ['Which letter contains the famous “love chapter”, chapter 13?', '1 Corinthians', ['Romans', 'Ephesians', 'Hebrews']],
                ['On which island was Paul shipwrecked on his way to Rome?', 'Malta', ['Crete', 'Cyprus', 'Patmos']],
                ['In which city did Paul live under house arrest at the end of Acts?', 'Rome', ['Jerusalem', 'Caesarea', 'Antioch']],
            ]),
            self::game('Revelation & the Last Things', 'church', '🌅', 'The last book of the Bible and the hope it gives.', [
                ['Who wrote the book of Revelation?', 'John', ['Paul', 'Peter', 'Luke']],
                ['On which island was John when he received the vision?', 'Patmos', ['Cyprus', 'Crete', 'Malta']],
                ['How many churches did Jesus write to at the start of Revelation?', 'Seven', ['Three', 'Ten', 'Twelve']],
                ['Which church was told, “You have abandoned the love you had at first”?', 'Ephesus', ['Smyrna', 'Laodicea', 'Philadelphia']],
                ['Which church was “lukewarm”, neither hot nor cold?', 'Laodicea', ['Sardis', 'Smyrna', 'Thyatira']],
                ['“Behold, I stand at the door and…”', 'Knock', ['Call', 'Wait', 'Watch']],
                ['Which is the last book of the Bible?', 'Revelation', ['Jude', 'Acts', 'Hebrews']],
                ['Which city comes down out of heaven from God in Revelation?', 'The new Jerusalem', ['Babylon the Great', 'Nineveh', 'Antioch']],
                ['“He will wipe away every…” what, from their eyes?', 'Tear', ['Sin', 'Shadow', 'Stain']],
                ['Jesus calls himself “the Alpha and the…”', 'Omega', ['Sigma', 'Delta', 'Beta']],
            ]),
        ];
    }

    protected static function explore(): array
    {
        return [
            self::game('Books of the Bible', 'explore', '📖', 'How well do you know the order and shape of the Bible?', [
                ['How many books are in the Bible?', '66', ['39', '27', '72']],
                ['How many books are in the Old Testament?', '39', ['27', '46', '66']],
                ['How many books are in the New Testament?', '27', ['39', '66', '21']],
                ['What is the first book of the Bible?', 'Genesis', ['Exodus', 'Psalms', 'Matthew']],
                ['What is the first book of the New Testament?', 'Matthew', ['Mark', 'Acts', 'John']],
                ['Which book comes right after the Gospel of Luke?', 'John', ['Acts', 'Romans', 'Hebrews']],
                ['Which book comes right after Acts?', 'Romans', ['Hebrews', 'Galatians', 'James']],
                ['Which of these is NOT one of the four Gospels?', 'Acts', ['Mark', 'Luke', 'John']],
                ['Which book has the most chapters?', 'Psalms', ['Isaiah', 'Genesis', 'Job']],
                ['Which Old Testament book comes just before Psalms?', 'Job', ['Proverbs', 'Esther', 'Ecclesiastes']],
            ]),
            self::game('Fruit, Gifts & Armour', 'explore', '🛡️', 'Gifts of the Spirit and the armour of God.', [
                ['Which of these is NOT a fruit of the Spirit?', 'Pride', ['Love', 'Patience', 'Self-control']],
                ['How many fruits of the Spirit are listed in Galatians 5?', 'Nine', ['Five', 'Seven', 'Twelve']],
                ['In the armour of God, what is the belt?', 'Truth', ['Righteousness', 'Peace', 'Faith']],
                ['In the armour of God, what is the shield?', 'Faith', ['Truth', 'Salvation', 'Peace']],
                ['In the armour of God, what is the sword of the Spirit?', 'The word of God', ['Prayer', 'Faith', 'Truth']],
                ['In the armour of God, what is the helmet?', 'Salvation', ['Faith', 'Peace', 'Righteousness']],
                ['In the armour of God, what is the breastplate?', 'Righteousness', ['Truth', 'Faith', 'Salvation']],
                ['“And now these three remain: faith, hope and love. But the greatest of these is…”', 'Love', ['Faith', 'Hope', 'Wisdom']],
                ['Which of these is a spiritual gift named in 1 Corinthians 12?', 'Prophecy', ['Poetry', 'Architecture', 'Navigation']],
                ['“Against such things there is no…”', 'Law', ['Hope', 'Sin', 'End']],
            ]),
            self::game('Bible Lands & Places', 'explore', '🗺️', 'Rivers, seas, mountains and cities of the Bible.', [
                ['Where did Jesus grow up?', 'Nazareth', ['Bethlehem', 'Cana', 'Capernaum']],
                ['Which sea did Jesus walk on?', 'The Sea of Galilee', ['The Dead Sea', 'The Red Sea', 'The Mediterranean Sea']],
                ['In which river was Jesus baptised?', 'The Jordan', ['The Nile', 'The Euphrates', 'The Tigris']],
                ['On which mountains did Noah’s ark come to rest?', 'The mountains of Ararat', ['Sinai', 'Carmel', 'Hermon']],
                ['Toward which city did Jonah sail to flee from God?', 'Tarshish', ['Nineveh', 'Tyre', 'Damascus']],
                ['Which city did Abraham leave to go to the land God would show him?', 'Ur of the Chaldeans', ['Babylon', 'Nineveh', 'Memphis']],
                ['In which country did Joseph become a ruler?', 'Egypt', ['Babylon', 'Assyria', 'Canaan']],
                ['Near which city did Saul meet Jesus on the road?', 'Damascus', ['Tarsus', 'Antioch', 'Jerusalem']],
                ['Which city was Paul’s hometown?', 'Tarsus', ['Antioch', 'Corinth', 'Philippi']],
                ['Which village were two disciples walking to when the risen Jesus joined them on the road?', 'Emmaus', ['Bethany', 'Cana', 'Jericho']],
            ]),
            self::game('Numbers in the Bible', 'explore', '🔢', 'Forty days, twelve tribes, three days and more.', [
                ['How many days and nights did the rain fall in Noah’s flood?', 'Forty', ['Seven', 'Ten', 'Fifty']],
                ['How many years did Israel wander in the wilderness?', 'Forty', ['Seven', 'Twelve', 'Seventy']],
                ['How many days did Jesus fast in the wilderness?', 'Forty', ['Seven', 'Three', 'Twenty']],
                ['How many tribes of Israel were there?', 'Twelve', ['Seven', 'Ten', 'Fourteen']],
                ['How many days was Jonah inside the great fish?', 'Three', ['One', 'Seven', 'Forty']],
                ['For how many pieces of silver was Jesus betrayed?', 'Thirty', ['Ten', 'Twelve', 'Forty']],
                ['How many days had Lazarus been in the tomb when Jesus came?', 'Four', ['Two', 'Three', 'Seven']],
                ['How many chapters are in the book of Psalms?', '150', ['100', '119', '66']],
                ['Who lived the longest of anyone in the Bible?', 'Methuselah, 969 years', ['Noah, 950 years', 'Adam, 930 years', 'Enoch, 365 years']],
                ['How many days did God work at creation before he rested?', 'Six', ['Five', 'Seven', 'Ten']],
            ]),
            self::game('Animals & Nature in Scripture', 'explore', '🐑', 'Talking donkeys, feeding ravens and a dove at the Jordan.', [
                ['Which animal spoke to Balaam?', 'A donkey', ['A lion', 'A snake', 'A raven']],
                ['Which birds fed Elijah by the brook Cherith?', 'Ravens', ['Doves', 'Eagles', 'Sparrows']],
                ['What swallowed Jonah?', 'A great fish', ['A crocodile', 'A sea serpent', 'A shark']],
                ['Which animal did Samson kill with his bare hands?', 'A lion', ['A bear', 'A wolf', 'A leopard']],
                ['What did John the Baptist eat in the wilderness?', 'Locusts and wild honey', ['Fish and bread', 'Figs and olives', 'Lamb and herbs']],
                ['What did the Spirit look like when Jesus was baptised?', 'A dove', ['A flame', 'An eagle', 'A cloud']],
                ['Which bird crowed after Peter denied Jesus?', 'A rooster', ['A dove', 'An owl', 'A raven']],
                ['Which kind of tree did Zacchaeus climb?', 'A sycamore', ['A fig tree', 'A palm tree', 'A cedar']],
                ['Which tree did Jesus curse because it had no fruit?', 'A fig tree', ['An olive tree', 'A palm tree', 'A vine']],
                ['Who called Jesus “the Lamb of God”?', 'John the Baptist', ['Peter', 'Paul', 'Gabriel']],
            ]),
        ];
    }

    protected static function uganda(): array
    {
        return [
            self::game('Faith in Uganda', 'uganda', '🇺🇬', 'The story of the gospel in Uganda: the missionaries, the martyrs and a nation that prays.', [
                ['In which year did the first Anglican (CMS) missionaries reach Buganda?', '1877', ['1844', '1894', '1962']],
                ['Which Catholic missionary society arrived in Buganda in 1879?', 'The White Fathers', ['The Jesuits', 'The Franciscans', 'The Benedictines']],
                ['Which Kabaka was on the throne when the Uganda Martyrs were killed?', 'Mwanga II', ['Mutesa I', 'Daudi Chwa', 'Kabaka Chwa']],
                ['Where were many of the martyrs burned in 1886?', 'Namugongo', ['Entebbe', 'Rubaga', 'Mengo']],
                ['On which date is Uganda Martyrs’ Day celebrated each year?', '3 June', ['9 October', '25 December', '1 January']],
                ['Which pope declared the Uganda Martyrs saints in 1964?', 'Paul VI', ['John Paul II', 'Francis', 'Pius XII']],
                ['Which missionary bishop was killed in 1885 on his way to Buganda?', 'James Hannington', ['Alfred Tucker', 'Janani Luwum', 'Charles Lwanga']],
                ['Which Archbishop of Uganda was killed in 1977 for speaking out?', 'Janani Luwum', ['Festo Kivengere', 'Erica Sabiti', 'Desmond Tutu']],
                ['What is Uganda’s national motto?', 'For God and my country', ['Unity and faith', 'Peace and light', 'In God we trust']],
                ['Which cathedral on Namirembe Hill in Kampala belongs to the Church of Uganda?', 'Namirembe Cathedral', ['Rubaga Cathedral', 'Kololo Chapel', 'Nakasero Church']],
            ]),
        ];
    }
}
